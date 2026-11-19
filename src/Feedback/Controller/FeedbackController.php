<?php

namespace App\Feedback\Controller;

use App\Compte\Entity\User;
use App\Feedback\Service\FeedbackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Feedback nourricier : la page du membre (lien, synthèse) et le formulaire public de ses proches. */
class FeedbackController extends AbstractController
{
    public function __construct(private readonly FeedbackService $feedback, private readonly \App\Shared\Security\RateLimits $limits)
    {
    }

    #[Route('/mes-tests/feedback', name: 'app_feedback', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $request = $this->feedback->activeRequest($user);

        return $this->render('feedback/index.html.twig', [
            'request' => $request,
            'url' => $request ? $this->generateUrl('app_feedback_form', ['token' => $request->getToken()], UrlGeneratorInterface::ABSOLUTE_URL) : null,
            'summary' => $request ? $this->feedback->summary($request) : null,
            'min' => FeedbackService::MIN_FOR_DETAIL,
        ]);
    }

    #[Route('/mes-tests/feedback/ouvrir', name: 'app_feedback_open', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function open(Request $request): Response
    {
        if ($this->isCsrfTokenValid('feedback', $request->request->getString('_csrf_token'))) {
            $this->feedback->open($this->getUser(), $request->request->getBoolean('forceRealName'));
            $this->addFlash('success', 'Votre lien est prêt : envoyez-le à 5 à 10 personnes qui vous connaissent bien.');
        }

        return $this->redirectToRoute('app_feedback');
    }

    #[Route('/mes-tests/feedback/fermer', name: 'app_feedback_close', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function close(Request $request): Response
    {
        $open = $this->feedback->activeRequest($this->getUser());
        if ($open && $this->isCsrfTokenValid('feedback', $request->request->getString('_csrf_token'))) {
            $this->feedback->close($open);
            $this->addFlash('success', 'Demande fermée : le lien ne fonctionne plus. Vous pouvez en créer une nouvelle quand vous voulez.');
        }

        return $this->redirectToRoute('app_feedback');
    }

    /** Formulaire public envoyé aux proches. */
    #[Route('/avis/{token}', name: 'app_feedback_form', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET', 'POST'])]
    public function form(string $token, Request $request): Response
    {
        $feedbackRequest = $this->feedback->findOpen($token) ?? throw $this->createNotFoundException('Cette demande de feedback est fermée.');
        $owner = $feedbackRequest->getUser();
        if ($this->getUser() === $owner) {
            return $this->redirectToRoute('app_feedback');
        }
        $session = $request->getSession();
        $doneKey = 'feedback_done_'.$token;
        $errors = [];
        $data = ['relationship' => '', 'qualities' => [], 'strengths' => '', 'improvements' => '', 'name' => ''];

        if ($request->isMethod('POST')) {
            // Anti-flood de ce formulaire public : par adresse et lien, puis par adresse (tous liens confondus)
            $ip = $request->getClientIp() ?? 'inconnue';
            $this->limits->require('public_form', $ip.'|'.$token);
            $this->limits->require('public_form_ip', $ip);
            $p = $request->request;
            if ('' !== $p->getString('website')) { // champ piège
                return $this->render('feedback/thanks.html.twig', ['firstName' => $owner->getDisplayName()]);
            }
            $data = ['relationship' => $p->getString('relationship'), 'qualities' => $p->all('qualities'), 'strengths' => $p->getString('strengths'),
                'improvements' => $p->getString('improvements'), 'name' => $p->getString('name')];
            if (!$this->isCsrfTokenValid('feedback_form', $p->getString('_csrf_token'))) {
                $errors[] = 'La page a expiré, merci de réessayer.';
            } elseif ($session->get($doneKey)) {
                $errors[] = 'Vous avez déjà répondu. Merci !';
            } else {
                $errors = $this->feedback->submit($feedbackRequest, $data);
            }
            if ([] === $errors) {
                $session->set($doneKey, true);

                return $this->render('feedback/thanks.html.twig', ['firstName' => $owner->getDisplayName()]);
            }
        }

        return $this->render('feedback/form.html.twig', [
            'firstName' => $owner->getDisplayName(), 'request' => $feedbackRequest, 'data' => $data, 'errors' => $errors,
            'qualities' => FeedbackService::QUALITIES, 'relationships' => FeedbackService::RELATIONSHIPS, 'maxQualities' => FeedbackService::MAX_QUALITIES,
            'alreadyDone' => (bool) $session->get($doneKey),
        ], new Response(status: [] === $errors ? 200 : 422));
    }
}
