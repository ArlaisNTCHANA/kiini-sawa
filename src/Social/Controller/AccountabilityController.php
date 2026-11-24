<?php

namespace App\Social\Controller;

use App\Compte\Entity\User;
use App\Shared\Security\RateLimits;
use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\AccountabilityNudge;
use App\Social\Service\AccountabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Engagements de redevabilité : pages de la personne (/social/redevabilite) et pages publiques du partenaire,
 * ouvertes par un lien personnel reçu par e-mail (/redevabilite/...), sans compte.
 */
class AccountabilityController extends AbstractController
{
    private const TOKEN = '[a-f0-9]{48}';

    public function __construct(private readonly AccountabilityService $service, private readonly RateLimits $limits)
    {
    }

    // ── La personne ────────────────────────────────────────────

    #[Route('/social/redevabilite', name: 'app_accountability', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->user();
        $commitments = $this->service->commitmentsOf($user);

        return $this->render('social/accountability/index.html.twig', [
            'commitments' => $commitments,
            'stats' => array_combine(array_map(fn ($c) => $c->getId(), $commitments), array_map(fn ($c) => $this->service->stats($c), $commitments)) ?: [],
            'nextAllowed' => array_combine(array_map(fn ($c) => $c->getId(), $commitments), array_map(fn ($c) => $this->service->nextNudgeAllowedAt($c), $commitments)) ?: [],
            'asPartner' => $this->service->commitmentsAsPartner($user),
            'canCreate' => $this->service->canCreate($user),
            'actions' => AccountabilityNudge::ACTIONS,
        ]);
    }

    #[Route('/social/redevabilite/nouveau', name: 'app_accountability_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $user = $this->user();
        if (!$this->service->canCreate($user)) {
            $this->addFlash('error', 'L\'offre gratuite comprend un engagement de redevabilité. Passez à PRO pour en ouvrir d\'autres, ou arrêtez celui en cours.');

            return $this->redirectToRoute('app_accountability');
        }

        $targets = $this->service->targetsFor($user);
        // Arrivée depuis une leçon de l'Académie (?cible=HABIT:12 ou GOAL:7) : le rituel ou l'objectif est déjà choisi,
        // seulement s'il appartient bien à la personne
        $data = [];
        if (preg_match('/^(HABIT|GOAL):(\d+)$/', $request->query->getString('cible'), $m)
            && \in_array((int) $m[2], array_column($targets['HABIT' === $m[1] ? 'habits' : 'goals'], 'id'), true)) {
            $data['target'] = $m[0];
        }

        return $this->render('social/accountability/new.html.twig', [
            'targets' => $targets,
            'partners' => $this->service->partnersOf($user),
            'data' => $data,
        ]);
    }

    /** Étape 2 : l'aperçu exact de ce qui sera envoyé, et la case « J'ai compris ». */
    #[Route('/social/redevabilite/apercu', name: 'app_accountability_preview', methods: ['POST'])]
    public function preview(Request $request): Response
    {
        $user = $this->user();
        $this->checkCsrf($request);
        $data = $this->formData($request);
        $targets = $this->service->targetsFor($user);
        $partners = $this->service->partnersOf($user);

        $label = $data['freeLabel'];
        foreach (['GOAL' => 'goals', 'HABIT' => 'habits'] as $prefix => $key) {
            foreach ($targets[$key] as $t) {
                if ($data['target'] === $prefix.':'.$t['id']) {
                    $label = $t['label'];
                }
            }
        }
        $partnerName = $data['partnerName'];
        foreach ($partners as $p) {
            if ((int) $data['connectionId'] === $p['id']) {
                $partnerName = $p['name'];
            }
        }
        $errors = [];
        if ('' === trim((string) $label)) {
            $errors[] = 'Choisissez un objectif, un rituel, ou écrivez votre engagement.';
        }
        if ('' === trim((string) $partnerName) || (0 === (int) $data['connectionId'] && !filter_var($data['partnerEmail'], \FILTER_VALIDATE_EMAIL))) {
            $errors[] = 'Choisissez un partenaire, ou indiquez son prénom et son adresse e-mail.';
        }
        if ($errors) {
            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }

            return $this->render('social/accountability/new.html.twig', ['targets' => $targets, 'partners' => $partners, 'data' => $data], new Response('', 422));
        }

        return $this->render('social/accountability/preview.html.twig', [
            'data' => $data,
            'label' => $label,
            'partnerName' => $partnerName,
            'owner' => $user->getDisplayName(),
            'actions' => AccountabilityNudge::ACTIONS,
            'minDays' => AccountabilityCommitment::DEFAULT_MIN_DAYS,
            'targetType' => str_starts_with($data['target'], 'HABIT:') ? 'HABIT' : (str_starts_with($data['target'], 'GOAL:') ? 'GOAL' : 'FREE'),
        ]);
    }

    #[Route('/social/redevabilite/creer', name: 'app_accountability_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->checkCsrf($request);
        $data = $this->formData($request) + ['consent' => $request->request->getBoolean('consent')];
        try {
            $c = $this->service->create($this->user(), $data);
            $this->addFlash('success', 'Engagement créé. '.$c->getPartnerName().' a reçu une invitation : rien d\'autre ne lui sera envoyé tant qu\'il ou elle n\'aura pas accepté.');
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_accountability_new');
        }

        return $this->redirectToRoute('app_accountability');
    }

    #[Route('/social/redevabilite/{id}/interpeller', name: 'app_accountability_nudge', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function nudge(AccountabilityCommitment $commitment, Request $request): Response
    {
        $this->checkCsrf($request);
        try {
            $this->service->requestNudge($commitment, $this->user(), $request->request->getString('message'));
            $this->addFlash('success', $commitment->getPartnerName().' a reçu votre demande. Vous en avez reçu une copie par e-mail.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_accountability', ['_fragment' => 'engagement-'.$commitment->getId()]);
    }

    #[Route('/social/redevabilite/{id}/{action}', name: 'app_accountability_manage', requirements: ['id' => '\d+', 'action' => 'pause|resume|stop'], methods: ['POST'])]
    public function manage(AccountabilityCommitment $commitment, string $action, Request $request): Response
    {
        $this->checkCsrf($request);
        try {
            $this->service->ownerAction($commitment, $this->user(), $action);
        } catch (\DomainException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('app_accountability', ['_fragment' => 'engagement-'.$commitment->getId()]);
    }

    // ── Le partenaire (lien personnel, sans compte) ────────────

    #[Route('/redevabilite/{token}', name: 'app_accountability_partner', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function partner(string $token, Request $request): Response
    {
        $c = $this->service->findByPartnerToken($token) ?? throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->limits->require('public_form_ip', (string) $request->getClientIp());
            if (!$this->isCsrfTokenValid('accountability_partner', $request->request->getString('_csrf_token'))) {
                return $this->redirectToRoute('app_accountability_partner', ['token' => $token]);
            }
            match ($request->request->getString('action')) {
                'accept' => $this->service->accept($c),
                'decline' => $this->service->decline($c),
                'stop' => $this->service->partnerStops($c),
                default => null,
            };

            return $this->redirectToRoute('app_accountability_partner', ['token' => $token]);
        }

        return $this->render('social/accountability/partner.html.twig', ['c' => $c, 'minDays' => $c->getMinDaysBetweenNudges()]);
    }

    #[Route('/redevabilite/reponse/{token}', name: 'app_accountability_respond', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function respond(string $token, Request $request): Response
    {
        $nudge = $this->service->findNudge($token) ?? throw $this->createNotFoundException();
        $error = null;
        if ($request->isMethod('POST')) {
            $this->limits->require('public_form_ip', (string) $request->getClientIp());
            if ($this->isCsrfTokenValid('accountability_respond', $request->request->getString('_csrf_token'))) {
                try {
                    $this->service->respond($nudge, $request->request->getString('action'), $request->request->getString('note'));

                    return $this->redirectToRoute('app_accountability_respond', ['token' => $token]);
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        } elseif ('' !== $request->query->getString('action') && null === $nudge->getRespondedAt()) {
            // Bouton de l'e-mail : l'action est présélectionnée, le partenaire confirme (pas d'enregistrement sur un simple GET)
            $preselected = $request->query->getString('action');
        }

        return $this->render('social/accountability/respond.html.twig', [
            'nudge' => $nudge,
            'c' => $nudge->getCommitment(),
            'actions' => AccountabilityNudge::ACTIONS,
            'preselected' => $preselected ?? $nudge->getResponseAction(),
            'error' => $error,
        ]);
    }

    /** @return array{target: string, freeLabel: string, why: string, connectionId: int, partnerName: string, partnerEmail: string, alertHabitDays: int, alertGoalDays: int, alertInactiveDays: int} */
    private function formData(Request $request): array
    {
        $r = $request->request;
        // Alertes automatiques. Formulaire de départ : case cochée + nombre de jours ; aperçu → création : nombre seul (0 = désactivée).
        $fromForm = $r->has('alertsForm');
        $days = static fn (string $k): int => $fromForm && !$r->getBoolean('alert'.$k) ? 0 : max(0, $r->getInt('alert'.$k.'Days'));

        return [
            'target' => $r->getString('target'),
            'freeLabel' => mb_substr(trim($r->getString('freeLabel')), 0, 255),
            'why' => mb_substr(trim($r->getString('why')), 0, 1000),
            'connectionId' => $r->getInt('connectionId'),
            'partnerName' => mb_substr(trim($r->getString('partnerName')), 0, 120),
            'partnerEmail' => mb_strtolower(trim($r->getString('partnerEmail'))),
            'alertHabitDays' => $days('Habit'),
            'alertGoalDays' => $days('Goal'),
            'alertInactiveDays' => $days('Inactive'),
        ];
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('accountability', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('La page a expiré. Merci de réessayer.');
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
