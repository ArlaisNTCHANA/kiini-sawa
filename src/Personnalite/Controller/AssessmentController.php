<?php

namespace App\Personnalite\Controller;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Personnalite\Psychometrie\IpipItemBank;
use App\Personnalite\Psychometrie\ReportBuilder;
use App\Shared\Service\PdfFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Questionnaires psychométriques : passation, rapport détaillé, export PDF.
 */
#[Route('/mes-tests', name: 'app_assessment_')]
class AssessmentController extends AbstractController
{
    public function __construct(private readonly AssessmentService $service, private readonly ReportBuilder $reports)
    {
    }

    #[Route('/questionnaire/{slug}', name: 'take', requirements: ['slug' => '[a-z-]+'], methods: ['GET', 'POST'])]
    public function take(string $slug, Request $request): Response
    {
        if (!Instruments::exists($slug)) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('assessment_'.$slug, $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Vos réponses n\'ont pas été enregistrées : merci de recommencer.');

                return $this->redirectToRoute('app_assessment_take', ['slug' => $slug]);
            }
            if ('disc' === Instruments::CATALOG[$slug]['bank'] && !$request->request->getBoolean('consent')) {
                $this->addFlash('error', 'Merci de confirmer que vous avez lu les conditions d\'utilisation du rapport.');

                return $this->redirectToRoute('app_assessment_take', ['slug' => $slug]);
            }
            $started = $request->request->getInt('startedAt');
            $seconds = $started > 0 ? max(0, time() - $started) : null;
            try {
                $assessment = $this->service->record($this->user(), $slug, $request->request->all('answers'), $seconds);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_assessment_take', ['slug' => $slug]);
            }

            return $this->redirectToRoute('app_assessment_report', ['id' => $assessment->getId()]);
        }

        return $this->render('personnalite/assessment/take.html.twig', [
            'slug' => $slug,
            'def' => Instruments::CATALOG[$slug],
            'items' => Instruments::items($slug),
            'scale' => IpipItemBank::SCALE,
            'startedAt' => time(),
        ]);
    }

    #[Route('/rapport/{id}', name: 'report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function report(Assessment $assessment, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($assessment);

        $data = $this->reports->build($assessment);

        return $this->render('personnalite/assessment/report.html.twig', $data + ['shareCard' => \App\Personnalite\Psychometrie\ShareCard::from($data),
            'addedActions' => $em->getRepository(\App\Personnalite\Entity\TestAction::class)->addedBy($this->user())]);
    }

    /** Partager le résultat (page publique : prénom + résultat principal) ou arrêter le partage. */
    #[Route('/rapport/{id}/partage', name: 'report_share', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function share(Assessment $assessment, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($assessment);
        if ($this->isCsrfTokenValid('assessment_share', $request->request->getString('_csrf_token'))) {
            'stop' === $request->request->getString('action') ? $assessment->stopSharing() : $assessment->share();
            $em->flush();
        }

        return $this->redirectToRoute('app_assessment_report', ['id' => $assessment->getId(), '_fragment' => 'partage']);
    }

    #[Route('/rapport/{id}/pdf', name: 'report_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function reportPdf(Assessment $assessment, PdfFactory $pdf): Response
    {
        $this->denyUnlessOwner($assessment);
        $data = $this->reports->build($assessment) + ['name' => $this->user()->getDisplayName()];

        return new Response($pdf->render($this->renderView($data['template'] ? 'personnalite/assessment/report/pdf.html.twig' : 'personnalite/assessment/report_pdf.html.twig', $data + ['body' => $data['template']])), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'kiini-sawa-'.$assessment->getInstrument().'-'.$assessment->getTakenAt()->format('Y-m-d').'.pdf'),
        ]);
    }

    private function denyUnlessOwner(Assessment $assessment): void
    {
        if ($assessment->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
