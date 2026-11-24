<?php

namespace App\Academie\Controller\Admin;

use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonDiagram;
use App\Academie\Repository\LessonDiagramRepository;
use App\Academie\Service\AcademyStudio;
use App\Academie\Service\DiagramModels;
use App\Academie\Service\LessonRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Atelier de schémas du studio : le fondateur compose un schéma à partir d'un modèle (cartes, comparaison, étapes,
 * « si… alors… », fraction, cycle), le voit en direct, puis le place dans une leçon par `[[schema:<code>]]`.
 */
#[Route('/admin/academie/schemas', name: 'admin_academie_diagram')]
#[IsGranted('ROLE_ADMIN')]
class AcademyDiagramAdminController extends AbstractController
{
    private const CSRF = 'admin_academie';

    public function __construct(private readonly EntityManagerInterface $em, private readonly LessonDiagramRepository $diagrams)
    {
    }

    #[Route('', name: 's', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('academie/admin/diagrams.html.twig', [
            'diagrams' => $this->diagrams->allOrdered(), 'builtins' => LessonRenderer::DIAGRAMS, 'models' => DiagramModels::MODELS,
        ]);
    }

    #[Route('/nouveau/{model}', name: '_new', requirements: ['model' => '[a-z]+'], methods: ['GET', 'POST'])]
    #[Route('/{id}', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ?string $model = null, ?int $id = null): Response
    {
        $diagram = $id ? ($this->diagrams->find($id) ?? throw $this->createNotFoundException()) : null;
        $model = $diagram?->getModel() ?? $model;
        if (!isset(DiagramModels::MODELS[$model])) {
            throw $this->createNotFoundException();
        }
        $state = ['title' => $diagram?->getTitle() ?? '', 'model' => $model, 'data' => $diagram?->getData() ?? DiagramModels::starter($model)];
        $error = null;

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $payload = json_decode($request->request->getString('payload'), true);
            $payload = \is_array($payload) ? $payload : [];
            $title = trim(strip_tags((string) ($payload['title'] ?? '')));
            $state = ['title' => $title, 'model' => $model, 'data' => \is_array($payload['data'] ?? null) ? $payload['data'] : []];
            try {
                if ('' === $title) {
                    throw new \InvalidArgumentException('Donnez un titre au schéma.');
                }
                $data = DiagramModels::normalize($model, $state['data']);
                $isNew = null === $diagram;
                $diagram ??= (new LessonDiagram())->setModel($model)->setCode($this->newCode($title));
                $diagram->setTitle(mb_substr($title, 0, 120))->setData($data);
                if ($isNew) {
                    $this->em->persist($diagram);
                }
                $this->em->flush();
                $this->addFlash('success', $isNew ? 'Schéma créé. Placez-le dans une leçon depuis le panneau « Rendre la leçon plus visuelle ».' : 'Schéma enregistré : il est à jour dans toutes les leçons qui l\'utilisent.');

                return $this->redirectToRoute('admin_academie_diagram_edit', ['id' => $diagram->getId()]);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('academie/admin/diagram.html.twig', [
            'diagram' => $diagram, 'state' => $state, 'error' => $error, 'def' => DiagramModels::MODELS[$model],
            'icons' => DiagramModels::ICONS, 'tones' => DiagramModels::TONES, 'usedIn' => $diagram ? $this->usedIn($diagram) : [],
        ]);
    }

    /** Aperçu en direct : le schéma tel qu'il apparaîtra, avant enregistrement (rien n'est écrit). */
    #[Route('/apercu', name: '_preview', methods: ['POST'])]
    public function preview(Request $request, LessonRenderer $renderer): Response
    {
        $this->checkCsrf($request);
        $payload = json_decode($request->request->getString('payload'), true);
        $payload = \is_array($payload) ? $payload : [];
        $model = (string) ($payload['model'] ?? '');
        try {
            $data = DiagramModels::normalize($model, \is_array($payload['data'] ?? null) ? $payload['data'] : []);
        } catch (\InvalidArgumentException $e) {
            return new Response('<p class="ks-alert ks-alert--warning">'.htmlspecialchars($e->getMessage()).'</p>');
        }

        return new Response($renderer->renderCustom(mb_substr(trim(strip_tags((string) ($payload['title'] ?? ''))), 0, 120), $model, $data));
    }

    #[Route('/{id}/supprimer', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $diagram = $this->diagrams->find($id) ?? throw $this->createNotFoundException();
        $used = $this->usedIn($diagram);
        if ([] !== $used) {
            $this->addFlash('error', 'Ce schéma est encore placé dans : '.implode(', ', array_map(fn (Lesson $l) => '« '.$l->getTitle().' »', $used)).'. Retirez-le d\'abord de ces leçons.');

            return $this->redirectToRoute('admin_academie_diagram_edit', ['id' => $id]);
        }
        $this->em->remove($diagram);
        $this->em->flush();
        $this->addFlash('success', 'Schéma supprimé.');

        return $this->redirectToRoute('admin_academie_diagrams');
    }

    /** @return list<Lesson> leçons dont le texte place ce schéma */
    private function usedIn(LessonDiagram $diagram): array
    {
        return $this->em->createQueryBuilder()->select('l')->from(Lesson::class, 'l')
            ->where('l.contentMarkdown LIKE :token')->setParameter('token', '%'.addcslashes($diagram->token(), '%_').'%')
            ->getQuery()->getResult();
    }

    /** Code unique, tiré du titre, qui ne reprend jamais celui d'un schéma dessiné dans le code. */
    private function newCode(string $title): string
    {
        $base = AcademyStudio::slugify($title) ?: 'schema';
        $base = mb_substr($base, 0, 70);
        $code = $base;
        for ($i = 2; isset(LessonRenderer::DIAGRAMS[$code]) || null !== $this->diagrams->findOneBy(['code' => $code]); ++$i) {
            $code = $base.'-'.$i;
        }

        return $code;
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            throw new AccessDeniedHttpException('La page a expiré, merci de recharger et de réessayer.');
        }
    }
}
