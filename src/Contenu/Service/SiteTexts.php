<?php

namespace App\Contenu\Service;

use App\Compte\Entity\User;
use App\Contenu\Entity\SiteText;
use App\Contenu\Entity\SiteTextVersion;
use App\Contenu\Repository\SiteTextRepository;
use App\Contenu\Repository\SiteTextVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Textes du site modifiables dans l'administration, avec brouillon puis publication.
 *
 * Lecture : le texte publié s'il existe, sinon le texte d'origine du code. Un administrateur en « mode aperçu » voit en plus les brouillons.
 * Écriture : enregistrer un brouillon → (relire en aperçu) → publier (la version est gardée) ; on peut toujours revenir en arrière
 * ou au texte d'origine.
 */
final class SiteTexts
{
    public const PREVIEW_KEY = 'site_texts_preview';

    /** @var ?array<string, SiteText> */
    private ?array $map = null;

    public function __construct(
        private readonly SiteTextRepository $texts,
        private readonly SiteTextVersionRepository $versions,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requests,
        private readonly Security $security,
        private readonly SiteTextCatalog $catalog,
    ) {
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    public function isPreview(): bool
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return false; // ligne de commande, requête sans session
        }
        $session = $request->getSession();

        return $session->has(self::PREVIEW_KEY) && $session->get(self::PREVIEW_KEY) && $this->security->isGranted('ROLE_ADMIN');
    }

    public function setPreview(bool $on): void
    {
        $this->requests->getSession()->set(self::PREVIEW_KEY, $on);
    }

    /** Le texte à afficher : brouillon (aperçu administrateur), sinon publié, sinon texte d'origine. */
    public function get(string $code, string $default): string
    {
        $row = $this->map()[$code] ?? null;
        if ($row && $this->isPreview() && null !== $row->getDraft()) {
            return $row->getDraft();
        }

        return $row?->getPublished() ?? $default;
    }

    /** Idem pour un document long dont le texte d'origine est un fichier de `content/textes/`. */
    public function document(string $code): string
    {
        return $this->get($code, $this->catalog->documentDefault($code) ?? '');
    }

    /** Le texte en ligne (sans brouillon), utile aux contrôles (ex. passages « À COMPLÉTER » restants). */
    public function published(string $code, string $default): string
    {
        return ($this->map()[$code] ?? null)?->getPublished() ?? $default;
    }

    /** Le document en ligne (sans brouillon) : texte publié, sinon fichier d'origine. */
    public function publishedDocument(string $code): string
    {
        return $this->published($code, $this->catalog->documentDefault($code) ?? '');
    }

    /** @return array<string, SiteText> */
    public function rows(): array
    {
        return $this->map();
    }

    public function row(string $code): ?SiteText
    {
        return $this->map()[$code] ?? null;
    }

    /** @return list<SiteTextVersion> */
    public function history(string $code): array
    {
        return $this->versions->history($code);
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    public function saveDraft(string $code, string $content, ?User $by): void
    {
        $this->assertKnown($code);
        $row = $this->row($code) ?? $this->create($code);
        $row->setDraft($this->clean($content), $by);
        $this->em->flush();
    }

    public function discardDraft(string $code): void
    {
        $this->row($code)?->discardDraft();
        $this->em->flush();
    }

    /** Met le brouillon en ligne (ou, si on donne un texte, ce texte). Retourne false s'il n'y avait rien à publier. */
    public function publish(string $code, ?User $by, ?string $content = null): bool
    {
        $this->assertKnown($code);
        $row = $this->row($code);
        $content = null !== $content ? $this->clean($content) : $row?->getDraft();
        if (null === $content) {
            return false;
        }
        $row ??= $this->create($code);
        $row->publish($content, $by);
        $this->em->persist(new SiteTextVersion($code, $content, SiteTextVersion::PUBLICATION, $by));
        $this->em->flush();

        return true;
    }

    /** Publie tous les brouillons ; retourne le nombre de textes publiés. */
    public function publishAll(?User $by): int
    {
        $n = 0;
        foreach ($this->map() as $code => $row) {
            if (null !== $row->getDraft() && $this->catalog->find($code)) {
                $this->publish($code, $by);
                ++$n;
            }
        }

        return $n;
    }

    /** Revient au texte d'origine du code (la version publiée est gardée dans l'historique). */
    public function revertToOriginal(string $code, ?User $by): void
    {
        $this->assertKnown($code);
        $row = $this->row($code);
        if (!$row || null === $row->getPublished()) {
            return;
        }
        $this->em->persist(new SiteTextVersion($code, $row->getPublished(), SiteTextVersion::ORIGINE, $by));
        $row->publish(null, $by);
        $this->em->flush();
    }

    /** Remet une ancienne version dans le brouillon (à relire puis publier). */
    public function restoreToDraft(string $code, int $versionId, ?User $by): bool
    {
        $v = $this->versions->find($versionId);
        if (!$v || $v->getCode() !== $code) {
            return false;
        }
        $this->saveDraft($code, $v->getContent(), $by);

        return true;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private function create(string $code): SiteText
    {
        $row = new SiteText($code);
        $this->em->persist($row);
        $this->map();
        $this->map[$code] = $row;

        return $row;
    }

    private function assertKnown(string $code): void
    {
        if (!$this->catalog->find($code)) {
            throw new \InvalidArgumentException('Texte inconnu : '.$code);
        }
    }

    /** Fins de ligne normalisées, espaces de bord retirés, longueur bornée. */
    private function clean(string $content): string
    {
        return mb_substr(trim(str_replace("\r\n", "\n", $content)), 0, 60000);
    }

    /** @return array<string, SiteText> */
    private function map(): array
    {
        if (null === $this->map) {
            $this->map = $this->texts->map();
        }

        return $this->map;
    }
}
