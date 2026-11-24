<?php

namespace App\Feedback\Controller;

use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Feedback\Entity\FeedbackRequest;
use App\Feedback\Service\FeedbackService;
use App\Shared\Security\RateLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Feedback nourricier : la page du membre (lien, message à partager, synthèse, demandes passées) et les pages publiques
 * vues par ses proches (formulaire, remerciement, lien terminé). Pour beaucoup, c'est la première rencontre avec KIINI SAWA.
 */
class FeedbackController extends AbstractController
{
    public function __construct(
        private readonly FeedbackService $feedback,
        private readonly RateLimits $limits,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/mes-tests/feedback', name: 'app_feedback', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $requests = $this->feedback->requestsOf($user);
        $current = $requests[0] ?? null;
        // Une demande fermée ou terminée garde ses résultats ; on propose d'en créer une nouvelle à côté
        $url = $current ? $this->publicUrl($current) : null;

        return $this->render('feedback/index.html.twig', [
            'request' => $current,
            'open' => $current?->isOpen() ?? false,
            'url' => $url,
            'messages' => $current && $current->isOpen() ? ['tu' => $this->feedback->shareMessage($current, $url, 'tu'), 'vous' => $this->feedback->shareMessage($current, $url, 'vous')] : null,
            'emailSubject' => $this->feedback->text('feedback.message.email_sujet'),
            'expiresLabel' => $current ? FeedbackService::frenchDate($current->getExpiresAt()) : null,
            'canExtend' => $current ? $this->feedback->canExtend($current) : false,
            'summary' => $current ? $this->feedback->summary($current) : null,
            'past' => array_map(fn (FeedbackRequest $r) => ['request' => $r, 'count' => $this->feedback->responseCount($r)], \array_slice($requests, 1)),
            'needsFirstName' => '' === trim((string) $user->getUserProfile()?->getFirstName()),
            'min' => FeedbackService::MIN_FOR_DETAIL,
        ]);
    }

    /** Résultats d'une demande passée. */
    #[Route('/mes-tests/feedback/demande/{id}', name: 'app_feedback_past', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function past(int $id): Response
    {
        $request = $this->em->find(FeedbackRequest::class, $id);
        if (null === $request || $request->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        return $this->render('feedback/past.html.twig', ['request' => $request, 'summary' => $this->feedback->summary($request), 'min' => FeedbackService::MIN_FOR_DETAIL]);
    }

    #[Route('/mes-tests/feedback/ouvrir', name: 'app_feedback_open', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function open(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('feedback', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('app_feedback');
        }
        // Le prénom est ce que voient les proches : sans lui, on afficherait le début de l'adresse e-mail
        if ('' === trim((string) $user->getUserProfile()?->getFirstName())) {
            $firstName = mb_substr(trim(strip_tags($request->request->getString('firstName'))), 0, 60);
            if ('' === $firstName) {
                $this->addFlash('error', 'Indiquez votre prénom : c\'est lui que vos proches verront.');

                return $this->redirectToRoute('app_feedback');
            }
            $profile = $user->getUserProfile() ?? (new UserProfile())->setUser($user);
            $profile->setFirstName($firstName);
            $user->setUserProfile($profile);
            $this->em->persist($profile);
            $this->em->flush();
        }
        $this->feedback->open($user, $request->request->getBoolean('forceRealName'));
        $this->addFlash('success', 'Votre lien est prêt : envoyez-le à 5 à 10 personnes qui vous connaissent bien.');

        return $this->redirectToRoute('app_feedback');
    }

    #[Route('/mes-tests/feedback/prolonger', name: 'app_feedback_extend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function extend(Request $request): Response
    {
        $current = $this->feedback->latestRequest($this->getUser());
        if ($current && $this->isCsrfTokenValid('feedback', $request->request->getString('_csrf_token'))) {
            if ($this->feedback->extend($current)) {
                $this->addFlash('success', 'Votre lien est prolongé jusqu\'au '.FeedbackService::frenchDate($current->getExpiresAt()).'.');
            } else {
                $this->addFlash('error', 'Ce lien ne peut pas être prolongé davantage. Vous pouvez en créer un nouveau.');
            }
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
            $this->addFlash('success', 'Demande fermée : le lien ne fonctionne plus. Vos résultats restent visibles ici.');
        }

        return $this->redirectToRoute('app_feedback');
    }

    /** Formulaire public envoyé aux proches. */
    #[Route('/avis/{token}', name: 'app_feedback_form', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET', 'POST'])]
    public function form(string $token, Request $request): Response
    {
        $feedbackRequest = $this->feedback->findByToken($token);
        if (null === $feedbackRequest) {
            return $this->render('feedback/closed.html.twig', ['firstName' => null, 'expired' => false], new Response(status: 404));
        }
        $owner = $feedbackRequest->getUser();
        if ($this->getUser() === $owner) {
            return $this->redirectToRoute('app_feedback');
        }
        if ($request->isMethod('POST')) {
            // Anti-flood de ce formulaire public : par adresse et lien, puis par adresse (tous liens confondus)
            $ip = $request->getClientIp() ?? 'inconnue';
            $this->limits->require('public_form', $ip.'|'.$token);
            $this->limits->require('public_form_ip', $ip);
        }
        $session = $request->getSession();
        $doneKey = 'feedback_done_'.$token;
        if ($session->get($doneKey)) {
            return $this->redirectToRoute('app_feedback_thanks', ['token' => $token]);
        }
        if (!$feedbackRequest->isOpen()) {
            return $this->render('feedback/closed.html.twig', ['firstName' => $owner->getDisplayName(), 'expired' => $feedbackRequest->isActive()], new Response(status: 410));
        }
        $errors = [];
        $data = ['relationship' => '', 'qualities' => [], 'otherQualities' => [], 'word' => '', 'notWord' => '', 'strengths' => '', 'improvements' => '', 'name' => ''];

        if ($request->isMethod('POST')) {
            $p = $request->request;
            if ('' !== $p->getString('website')) { // champ piège : on fait comme si tout allait bien
                $session->set($doneKey, true);

                return $this->redirectToRoute('app_feedback_thanks', ['token' => $token]);
            }
            $data = ['relationship' => $p->getString('relationship'), 'qualities' => $p->all('qualities'),
                'otherQualities' => array_values(array_filter(array_map(FeedbackService::cleanWord(...), array_filter($p->all('otherQualities'), 'is_string')), fn ($q) => '' !== $q)),
                'word' => $p->getString('word'), 'notWord' => $p->getString('notWord'), 'strengths' => $p->getString('strengths'),
                'improvements' => $p->getString('improvements'), 'name' => $p->getString('name')];
            if (!$this->isCsrfTokenValid('feedback_form', $p->getString('_csrf_token'))) {
                $errors[] = 'La page a expiré, merci de réessayer.';
            } else {
                $errors = $this->feedback->submit($feedbackRequest, $data);
            }
            if ([] === $errors) {
                $session->set($doneKey, true);

                // Redirection après l'envoi : sans elle, Turbo laisse le formulaire affiché et la personne croit que rien n'est parti
                return $this->redirectToRoute('app_feedback_thanks', ['token' => $token]);
            }
        }

        return $this->render('feedback/form.html.twig', [
            'firstName' => $owner->getDisplayName(), 'request' => $feedbackRequest, 'data' => $data, 'errors' => $errors,
            'qualities' => FeedbackService::QUALITIES, 'relationships' => FeedbackService::RELATIONSHIPS, 'maxQualities' => FeedbackService::MAX_QUALITIES, 'maxOwn' => FeedbackService::MAX_OWN_QUALITIES, 'wordLength' => FeedbackService::WORD_LENGTH,
            'expiresLabel' => FeedbackService::frenchDate($feedbackRequest->getExpiresAt()), 'hasExpiry' => null !== $feedbackRequest->getExpiresAt(),
        ], new Response(status: [] === $errors ? 200 : 422));
    }

    /** Remerciement (et invitation discrète à découvrir KIINI SAWA), seulement après une réponse depuis ce navigateur. */
    #[Route('/avis/{token}/merci', name: 'app_feedback_thanks', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET'])]
    public function thanks(string $token, Request $request): Response
    {
        $feedbackRequest = $this->feedback->findByToken($token);
        if (null === $feedbackRequest || !$request->getSession()->get('feedback_done_'.$token)) {
            return $this->redirectToRoute('app_feedback_form', ['token' => $token]);
        }

        return $this->render('feedback/thanks.html.twig', ['firstName' => $feedbackRequest->getUser()->getDisplayName()]);
    }

    /** Adresse publique du formulaire, à partager. */
    private function publicUrl(FeedbackRequest $request): string
    {
        return $this->generateUrl('app_feedback_form', ['token' => $request->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
