<?php

namespace App\Personnalite\Controller\Admin;

use App\Compte\Entity\User;
use App\Personnalite\Entity\ContentOverride;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Repository\ContentOverrideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Modifier les textes des tests (questions et rapports) sans toucher au calcul.
 * Le code reste la référence : « Revenir au texte d'origine » supprime la modification.
 */
#[Route('/admin/tests/textes')]
#[IsGranted('ROLE_ADMIN')]
final class ContentAdminController extends AbstractController
{
    private const LABELS = [
        'name' => 'Nom', 'tagline' => 'Accroche', 'keywords' => 'Mot-clé', 'portrait' => 'Portrait', 'strengths' => 'Force',
        'watch' => 'Point de vigilance', 'motivators' => 'Ce qui motive', 'drains' => 'Ce qui épuise', 'environment' => 'Environnement idéal',
        'work' => 'Au travail', 'family' => 'En famille et en couple', 'relations' => 'En couple et en famille', 'church' => 'À l\'Église',
        'leader' => 'Comme leader', 'decide' => 'Façon de décider', 'communicate' => 'Façon de communiquer', 'conflict' => 'Face au conflit',
        'signs' => 'Signaux d\'alerte', 'recharge' => 'Ce qui ressource', 'grow' => 'Pour grandir', 'figures' => 'Figure biblique',
        'actions' => 'Action', 'action' => 'Premier pas', 'text' => 'Texte', 'ref' => 'Référence', 'verse' => 'Verset', 'meditation' => 'Question à méditer',
        'motivation' => 'Motivation', 'fear' => 'Peur profonde', 'gift' => 'Don', 'growth' => 'Chemin de croissance', 'growthText' => 'En croissance',
        'stressText' => 'Sous pression', 'stress' => 'Sous pression', 'desc' => 'Description', 'serve' => 'Pistes de service', 'daily' => 'Au quotidien',
        'dangers' => 'Piège', 'read' => 'À lire', 'what' => 'Définition', 'high' => 'Niveau haut', 'mid' => 'Niveau moyen', 'low' => 'Niveau bas',
        'label' => 'Libellé', 'spirit' => 'Vie spirituelle', 'question' => 'Question', 'plan' => 'Plan KIINI SAWA', 'habits' => 'Habitude',
        'emotion' => 'Émotion clé', 'essence' => 'En une phrase', 'axis' => 'Axes', 'needs' => 'Besoin', 'pressure' => 'Sous pression',
        'adapted' => 'En adaptation', 'natural' => 'Réflexe naturel', 'tension' => 'Tension durable', 'cues' => 'Indice', 'speech' => 'Débit',
        'gesture' => 'Gestuelle', 'mail' => 'Mails', 'disagree' => 'Dans un désaccord', 'more' => 'Ce que le contexte demande en plus',
        'colorName' => 'Nom de la couleur', 'portraitShort' => 'Portrait court',
        // Fiches de personnages bibliques
        'title' => 'Titre', 'refs' => 'Passages', 'who' => 'Qui est-il', 'weakness' => 'Là où il est fragile', 'grace' => 'Comment Dieu le relève',
        'christ' => 'Le Christ au centre', 'step' => 'Un pas', 'why' => 'Pourquoi lui, pour vous', 'default' => 'Par défaut', 'profile' => 'Profil',
        'dimensions' => 'Dimension', 'traits' => 'Indice', 'absent' => 'Ce que le texte ne montre pas', 'limits' => 'Limites de la lecture',
        'solidity' => 'Solidité de la lecture', 'episodes' => 'Épisode', 'relecture' => 'Relecture (« en cours » ou « faite »)',
    ];

    public function __construct(private readonly ContentOverrideRepository $overrides, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_content_index')]
    public function index(): Response
    {
        $all = $this->overrides->map();
        $groups = [];
        foreach (Content::SOURCES as $key => [$test, $label]) {
            $modified = \count(array_filter(array_keys($all), fn ($p) => str_starts_with($p, $key.'.')));
            $groups[$test][$key] = ['label' => $label, 'count' => \count(Content::leaves($key)), 'modified' => $modified];
        }

        return $this->render('personnalite/admin/content_index.html.twig', ['groups' => $groups, 'total' => \count($all)]);
    }

    #[Route('/export', name: 'admin_content_export')]
    public function export(): Response
    {
        $rows = [];
        foreach ($this->overrides->findBy([], ['path' => 'ASC']) as $o) {
            $rows[$o->getPath()] = ['texte' => $o->getContent(), 'modifie_le' => $o->getUpdatedAt()->format('Y-m-d H:i')];
        }

        return new Response(json_encode($rows, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'kiini-sawa-textes-modifies-'.date('Y-m-d').'.json'),
        ]);
    }

    #[Route('/{source}', name: 'admin_content_source')]
    public function source(string $source, Request $request): Response
    {
        $this->assertSource($source);
        $q = mb_strtolower(trim($request->query->getString('q')));
        $modified = array_keys($this->overrides->byPrefix($source.'.'));
        $entries = [];
        foreach (Content::defaults($source) as $key => $value) {
            $leaves = Content::leaves($source, (string) $key);
            if (!$leaves) {
                continue;
            }
            $current = Content::get($source)[$key];
            if ('' !== $q && !str_contains(mb_strtolower($key.' '.json_encode($current, \JSON_UNESCAPED_UNICODE)), $q)) {
                continue;
            }
            $entries[$key] = [
                'title' => $this->title($key, $current),
                'fields' => \count($leaves),
                'modified' => \count(array_filter($modified, fn ($p) => str_starts_with($p, $source.'.'.$key.'.') || $p === $source.'.'.$key)),
            ];
        }

        return $this->render('personnalite/admin/content_source.html.twig', [
            'source' => $source, 'meta' => Content::SOURCES[$source], 'entries' => $entries, 'q' => $q,
        ]);
    }

    #[Route('/{source}/{entry}', name: 'admin_content_entry', methods: ['GET', 'POST'])]
    public function entry(string $source, string $entry, Request $request): Response
    {
        $this->assertSource($source);
        $defaults = Content::leaves($source, $entry);
        if (!$defaults) {
            throw $this->createNotFoundException();
        }
        $existing = $this->overrides->byPrefix($source.'.'.$entry.'.');
        if (isset(Content::defaults($source)[$entry]) && \is_string(Content::defaults($source)[$entry])) {
            $existing += $this->overrides->byPrefix($source.'.'.$entry);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('content_'.$source.'_'.$entry, $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré : vos modifications n\'ont pas été enregistrées.');

                return $this->redirectToRoute('admin_content_entry', ['source' => $source, 'entry' => $entry]);
            }
            $values = $request->request->all('f');
            $resets = $request->request->all('reset');
            /** @var User $user */
            $user = $this->getUser();
            $changed = 0;
            foreach ($defaults as $path => $default) {
                $key = base64_encode($path);
                $full = $source.'.'.$path;
                $value = trim(str_replace("\r\n", "\n", (string) ($values[$key] ?? $default)));
                $override = $existing[$full] ?? null;
                if (isset($resets[$key]) || '' === $value || $value === $default) {
                    if ($override) {
                        $this->em->remove($override);
                        ++$changed;
                    }
                    continue;
                }
                if (!$override) {
                    $this->em->persist(new ContentOverride($full, $value, $user));
                    ++$changed;
                } elseif ($override->getContent() !== $value) {
                    $override->update($value, $user);
                    ++$changed;
                }
            }
            $this->em->flush();
            Content::reset();
            $this->addFlash('success', $changed ? sprintf('%d texte(s) mis à jour. Les rapports affichent désormais vos textes.', $changed) : 'Aucun changement.');

            return $this->redirectToRoute('admin_content_entry', ['source' => $source, 'entry' => $entry]);
        }

        $fields = [];
        foreach ($defaults as $path => $default) {
            $o = $existing[$source.'.'.$path] ?? null;
            $fields[] = ['key' => base64_encode($path), 'label' => $this->label($path, $entry), 'default' => $default,
                'value' => $o?->getContent() ?? $default, 'override' => $o, 'long' => mb_strlen($default) > 70];
        }

        return $this->render('personnalite/admin/content_entry.html.twig', [
            'source' => $source, 'meta' => Content::SOURCES[$source], 'entry' => $entry,
            'title' => $this->title($entry, Content::get($source)[$entry]), 'fields' => $fields,
        ]);
    }

    private function assertSource(string $source): void
    {
        if (!isset(Content::SOURCES[$source])) {
            throw $this->createNotFoundException();
        }
    }

    private function title(string|int $key, mixed $value): string
    {
        $name = match (true) {
            \is_string($value) => $value,
            \is_array($value) && isset($value['name']) && \is_string($value['name']) => $value['name'],
            \is_array($value) && isset($value['label']) && \is_string($value['label']) => $value['label'],
            \is_array($value) && isset($value[1]) && \is_string($value[1]) && !isset($value[2]) => $value[1],
            \is_array($value) && isset($value[0]) && \is_string($value[0]) => implode(' · ', array_filter($value, 'is_string')),
            default => '',
        };
        $name = mb_strimwidth($name, 0, 90, '…');

        return (string) $key === $name ? $name : $key.($name ? ' · '.$name : '');
    }

    private function label(string $path, string $entry): string
    {
        $parts = \array_slice(explode('.', $path), 1);
        if (!$parts) {
            return 'Texte';
        }
        // Fiches bibliques : un indice = [le trait, ce que dit le texte, la référence] ; « ne montre pas » = [le point, le détail]
        if (preg_match('/^profile\.dimensions\.([DISC])\.traits\.(\d+)\.(\d)$/', $path = implode('.', $parts), $m)) {
            return 'Profil · '.$m[1].' · indice n° '.((int) $m[2] + 1).' · '.['Le trait', 'Ce que dit le texte', 'Référence'][(int) $m[3]];
        }
        if (preg_match('/^profile\.absent\.(\d+)\.(\d)$/', $path, $m)) {
            return 'Ce que le texte ne montre pas · n° '.((int) $m[1] + 1).' · '.['Le point', 'Le détail'][(int) $m[2]];
        }
        // Lectures par test (ennéagramme, dons, type) : lenses.<TEST:code>.traits.N.M ; lenses.<TEST:code>.absent.N.M
        if (preg_match('/^lenses\.([A-Z]+:[^.]+)\.traits\.(\d+)\.(\d)$/', $path, $m)) {
            return 'Lecture '.$m[1].' · indice n° '.((int) $m[2] + 1).' · '.['Le trait', 'Ce que dit le texte', 'Référence'][(int) $m[3]];
        }
        if (preg_match('/^lenses\.([A-Z]+:[^.]+)\.absent\.(\d+)\.(\d)$/', $path, $m)) {
            return 'Lecture '.$m[1].' · ce que le texte ne montre pas · n° '.((int) $m[2] + 1).' · '.['Le point', 'Le détail'][(int) $m[3]];
        }
        if (preg_match('/^lenses\.([A-Z]+:[^.]+)\.(label|definition|solidity|limits|why)$/', $path, $m)) {
            return 'Lecture '.$m[1].' · '.['label' => 'Titre', 'definition' => 'Définition', 'solidity' => 'Solidité', 'limits' => 'Limites', 'why' => 'Pourquoi lui, pour vous'][$m[2]];
        }
        if (preg_match('/^why\.(D|I|S|C|default)$/', $path, $m)) {
            return 'Pourquoi lui, pour vous · '.('default' === $m[1] ? 'texte par défaut' : 'profil '.$m[1]);
        }
        $out = [];
        foreach ($parts as $p) {
            $out[] = ctype_digit($p) ? 'n° '.((int) $p + 1) : (self::LABELS[$p] ?? $p);
        }

        return implode(' · ', $out);
    }
}
