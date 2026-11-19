<?php

namespace App\Contenu\Controller\Admin;

use App\Compte\Entity\User;
use App\Contenu\Service\SiteTextCatalog;
use App\Contenu\Service\SiteTexts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Modifier les textes du site sans toucher au code : brouillon → aperçu → publication, avec historique.
 * Le code reste le texte d'origine : « Revenir au texte d'origine » remet toujours le texte livré.
 */
#[Route('/admin/textes')]
#[IsGranted('ROLE_ADMIN')]
final class SiteTextAdminController extends AbstractController
{
    public const GROUPS = [
        'vitrine' => 'Page d\'accueil publique',
        'legal' => 'Pages légales',
    ];

    public function __construct(private readonly SiteTexts $texts, private readonly SiteTextCatalog $catalog)
    {
    }

    #[Route('', name: 'admin_site_texts', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $group = $request->query->getString('groupe');
        $status = $request->query->getString('etat');
        $rows = $this->texts->rows();
        $items = [];
        $groups = [];
        $drafts = 0;
        foreach ($this->catalog->all() as $code => $info) {
            $row = $rows[$code] ?? null;
            $g = SiteTextCatalog::group($code);
            $groups[$g] = ($groups[$g] ?? 0) + 1;
            $state = $row && null !== $row->getDraft() ? 'draft' : ($row && null !== $row->getPublished() ? 'modified' : 'original');
            $drafts += 'draft' === $state ? 1 : 0;
            $current = $row?->getPublished() ?? $info['default'];
            if (($group && $g !== $group) || ($status && $status !== $state)
                || ($q && !str_contains(mb_strtolower($code.' '.$current.' '.($row?->getDraft() ?? '')), mb_strtolower($q)))) {
                continue;
            }
            $items[] = ['code' => $code, 'group' => $g, 'state' => $state, 'format' => $info['format'], 'excerpt' => mb_strimwidth(preg_replace('/\s+/u', ' ', strip_tags($current)), 0, 140, '…'), 'at' => $row?->getDraftAt() ?? $row?->getPublishedAt()];
        }
        ksort($groups);

        return $this->render('contenu/admin/index.html.twig', [
            'items' => $items, 'groups' => $groups, 'groupLabels' => self::GROUPS, 'q' => $q, 'group' => $group, 'status' => $status,
            'drafts' => $drafts, 'total' => \count($this->catalog->all()), 'preview' => $this->texts->isPreview(),
        ]);
    }

    #[Route('/modifier', name: 'admin_site_text_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $code = $request->query->getString('code');
        $info = $this->catalog->find($code);
        if (!$info) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('site_text', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('admin_site_text_edit', ['code' => $code]);
            }
            $user = $this->getUser();
            \assert($user instanceof User);
            $content = $request->request->getString('content');
            switch ($request->request->getString('action')) {
                case 'draft':
                    $this->texts->saveDraft($code, $content, $user);
                    $this->addFlash('success', 'Brouillon enregistré. Il n\'est pas encore en ligne : relisez-le en mode aperçu, puis publiez.');
                    break;
                case 'publish':
                    $this->texts->publish($code, $user, $content);
                    $this->addFlash('success', 'Texte publié : il est en ligne.');
                    break;
                case 'discard':
                    $this->texts->discardDraft($code);
                    $this->addFlash('success', 'Brouillon abandonné.');
                    break;
                case 'revert':
                    $this->texts->revertToOriginal($code, $user);
                    $this->addFlash('success', 'Le texte d\'origine est de retour en ligne (la version précédente reste dans l\'historique).');
                    break;
                case 'restore':
                    $this->texts->restoreToDraft($code, $request->request->getInt('version'), $user)
                        ? $this->addFlash('success', 'Ancienne version remise dans le brouillon : relisez-la, puis publiez.')
                        : $this->addFlash('error', 'Version introuvable.');
                    break;
            }

            return $this->redirectToRoute('admin_site_text_edit', ['code' => $code]);
        }
        $row = $this->texts->row($code);

        return $this->render('contenu/admin/edit.html.twig', [
            'code' => $code, 'info' => $info, 'row' => $row, 'formats' => SiteTextCatalog::FORMATS,
            'value' => $row?->getDraft() ?? $row?->getPublished() ?? $info['default'],
            'history' => $this->texts->history($code), 'groupLabel' => self::GROUPS[SiteTextCatalog::group($code)] ?? SiteTextCatalog::group($code),
        ]);
    }

    #[Route('/apercu', name: 'admin_site_texts_preview', methods: ['POST'])]
    public function preview(Request $request): Response
    {
        if ($this->isCsrfTokenValid('site_text', $request->request->getString('_csrf_token'))) {
            $on = !$this->texts->isPreview();
            $this->texts->setPreview($on);
            $this->addFlash('success', $on ? 'Mode aperçu activé : en parcourant le site, vous voyez vos brouillons (vous seul).' : 'Mode aperçu désactivé.');
        }

        return $this->redirectToRoute('admin_site_texts');
    }

    #[Route('/publier-tout', name: 'admin_site_texts_publish_all', methods: ['POST'])]
    public function publishAll(Request $request): Response
    {
        if ($this->isCsrfTokenValid('site_text', $request->request->getString('_csrf_token'))) {
            $user = $this->getUser();
            \assert($user instanceof User);
            $n = $this->texts->publishAll($user);
            $this->addFlash('success', $n ? $n.' texte'.($n > 1 ? 's publiés' : ' publié').'.' : 'Aucun brouillon à publier.');
        }

        return $this->redirectToRoute('admin_site_texts');
    }
}
