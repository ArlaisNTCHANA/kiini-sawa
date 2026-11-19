<?php

namespace App\Organisation\Controller;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use App\Organisation\Entity\Membership;
use App\Organisation\Entity\Organisation;
use App\Organisation\Entity\SeatOrder;
use App\Organisation\Service\OrganisationService;
use App\Organisation\Service\PendingInvitation;
use App\PlanDeVie\Service\PlanDataProvider;
use App\Shared\Service\PdfFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Espace Organisation : tableau de bord, membres, rôles, invitations, places.
 */
class OrganisationController extends AbstractController
{
    public function __construct(private readonly OrganisationService $orgs, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/organisation', name: 'app_organisation_index', methods: ['GET'])]
    public function index(Request $request, Entitlements $entitlements): Response
    {
        $user = $this->user();
        $memberships = $this->orgs->activeMembershipsOf($user);
        if ([] === $memberships) {
            return $this->render('organisation/start.html.twig', [
                'packs' => $this->orgs->packs(),
                'types' => Organisation::TYPES,
            ]);
        }

        $current = $memberships[0];
        foreach ($memberships as $m) {
            if ($m->getOrganisation()->getId() === $request->query->getInt('org')) {
                $current = $m;
            }
        }
        $org = $current->getOrganisation();
        $members = $this->orgs->visibleMembers($current);
        $stats = [];
        foreach ($members as $m) {
            $stats[$m->getId()] = $this->orgs->stats($m->getUser());
        }
        $orders = $this->em->getRepository(SeatOrder::class)->findBy(['organisation' => $org], ['createdAt' => 'DESC']);
        $config = $entitlements->config();

        return $this->render('organisation/dashboard.html.twig', [
            'me' => $current,
            'memberships' => $memberships,
            'org' => $org,
            'members' => $members,
            'stats' => $stats,
            'summary' => $this->orgs->summary(array_values(array_filter($stats, fn ($k) => $k !== $current->getId(), \ARRAY_FILTER_USE_KEY))),
            'mentors' => array_filter($org->getActiveMemberships(), fn (Membership $m) => \in_array($m->getRole(), ['MENTOR', 'LEADER', 'OWNER'], true)),
            'inviteUrl' => $this->generateUrl('app_organisation_join', ['code' => $org->getInviteCode()], UrlGeneratorInterface::ABSOLUTE_URL),
            'packs' => $this->orgs->packs(),
            'orders' => $orders,
            'pendingOrder' => array_values(array_filter($orders, fn (SeatOrder $o) => $o->isPending()))[0] ?? null,
            'bankInstructions' => $config->getBankTransferInstructions(),
            'mobileMoneyInstructions' => $config->getMobileMoneyInstructions(),
        ]);
    }

    #[Route('/organisation/creer', name: 'app_organisation_create', methods: ['POST'])]
    public function create(Request $request, \App\Shared\Security\RateLimits $limits): Response
    {
        $name = trim($request->request->getString('name'));
        if (!$this->isCsrfTokenValid('org_create', $request->request->getString('_csrf_token')) || mb_strlen($name) < 2) {
            $this->addFlash('error', 'Indiquez le nom de votre organisation.');

            return $this->redirectToRoute('app_organisation_index');
        }
        // Au plus 2 organisations créées par compte et par jour (sinon, de nouvelles organisations gratuites multiplient les invitations)
        if (!$limits->allow('org_create', (string) $this->user()->getId())) {
            $this->addFlash('error', 'Vous avez déjà créé plusieurs organisations aujourd\'hui. Réessayez demain.');

            return $this->redirectToRoute('app_organisation_index');
        }
        $org = $this->orgs->create($this->user(), $name, $request->request->getString('type'));
        $this->addFlash('success', $org->getName().' est créée. Choisissez un pack de places, puis partagez le lien d\'invitation.');

        return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
    }

    /** Lien d'invitation : public, pour accueillir aussi ceux qui n'ont pas encore de compte. */
    #[Route('/rejoindre/{code}', name: 'app_organisation_join', requirements: ['code' => '[a-f0-9]{16}'], methods: ['GET', 'POST'])]
    public function join(string $code, Request $request): Response
    {
        $org = $this->em->getRepository(Organisation::class)->findOneBy(['inviteCode' => $code]) ?? throw $this->createNotFoundException('Ce lien d\'invitation n\'est plus valable.');
        $user = $this->getUser();
        if (!$user instanceof User) {
            // Après inscription ou connexion, l'utilisateur revient ici (voir PendingInvitation)
            $request->getSession()->set(PendingInvitation::SESSION_KEY, $code);

            return $this->render('organisation/join_anonymous.html.twig', ['org' => $org]);
        }

        $existing = $this->orgs->membershipOf($user, $org);
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('org_join', $request->request->getString('_csrf_token'))) {
            try {
                $this->orgs->join($user, $org, $request->request->getBoolean('sharePlans'));
                $request->getSession()->remove(PendingInvitation::SESSION_KEY);
                $this->addFlash('success', 'Bienvenue dans '.$org->getName().' !'.($org->isLicenseValid() ? ' Votre accès PRO est actif.' : ''));

                return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        $request->getSession()->remove(PendingInvitation::SESSION_KEY);

        return $this->render('organisation/join.html.twig', ['org' => $org, 'alreadyMember' => $existing?->isActive()]);
    }

    #[Route('/organisation/{id}/membre/{memberId}', name: 'app_organisation_member_update', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['POST'])]
    public function updateMember(Organisation $org, int $memberId, Request $request): Response
    {
        $me = $this->managerOf($org, $request);
        $member = $this->memberOf($org, $memberId);
        $action = $request->request->getString('action');

        if ('role' === $action) {
            $role = $request->request->getString('role');
            if ('OWNER' === $role && !$me->isOwner()) {
                $this->addFlash('error', 'Seul un responsable principal peut en nommer un autre.');
            } elseif ($member === $me && 'OWNER' !== $role && $me->isOwner() && 1 === \count(array_filter($org->getActiveMemberships(), fn ($m) => $m->isOwner()))) {
                $this->addFlash('error', 'Nommez un autre responsable principal avant de changer votre propre rôle.');
            } else {
                $member->setRole($role);
                $this->addFlash('success', $member->getUser()->getDisplayName().' est maintenant '.mb_strtolower($member->getRoleLabel()).'.');
            }
        } elseif ('mentor' === $action) {
            $mentorId = $request->request->getInt('mentor');
            $mentor = $mentorId ? $this->memberOf($org, $mentorId) : null;
            if ($mentor && 'MEMBER' === $mentor->getRole()) {
                $mentor->setRole('MENTOR');
            }
            $member->setMentor($mentor === $member ? null : $mentor);
            $this->addFlash('success', $mentor ? $member->getUser()->getDisplayName().' est confié(e) à '.$mentor->getUser()->getDisplayName().'.' : 'Accompagnateur retiré.');
        } elseif ('remove' === $action) {
            try {
                $this->orgs->leave($member);
                $this->addFlash('success', $member->getUser()->getDisplayName().' a été retiré(e) de l\'organisation. Sa place est libérée ; ses données personnelles restent les siennes.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        $this->em->flush();

        return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
    }

    /** Le membre choisit de partager (ou non) ses plans, ou quitte l'organisation. */
    #[Route('/organisation/{id}/moi', name: 'app_organisation_self', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function self(Organisation $org, Request $request): Response
    {
        $me = $this->orgs->membershipOf($this->user(), $org);
        if (!$me?->isActive() || !$this->isCsrfTokenValid('org_self', $request->request->getString('_csrf_token'))) {
            throw $this->createNotFoundException();
        }
        if ('leave' === $request->request->getString('action')) {
            try {
                $this->orgs->leave($me);
                $this->addFlash('success', 'Vous avez quitté '.$org->getName().'. Vos données restent les vôtres.');

                return $this->redirectToRoute('app_organisation_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $me->setSharePlans($request->request->getBoolean('sharePlans'));
            $this->em->flush();
            $this->addFlash('success', $me->isSharePlans() ? 'Vos responsables et votre accompagnateur peuvent lire vos plans (jamais votre journal).' : 'Vos plans ne sont plus partagés.');
        }

        return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
    }

    /** Lecture seule des plans d'un membre, avec son consentement. */
    #[Route('/organisation/{id}/membre/{memberId}/plan', name: 'app_organisation_member_plan', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['GET'])]
    public function memberPlan(Organisation $org, int $memberId, PlanDataProvider $plans): Response
    {
        $me = $this->orgs->membershipOf($this->user(), $org);
        $member = $this->memberOf($org, $memberId);
        if (!$me || !$this->orgs->canReadPlans($me, $member)) {
            throw $this->createAccessDeniedException('Ce membre ne partage pas ses plans avec vous.');
        }
        $data = $plans->getLifePlanData($member->getUser());

        return $this->render('plan_de_vie/visualize.html.twig', $data + [
            'variant' => 'SHARED',
            'ownerName' => $member->getUser()->getDisplayName(),
            'backUrl' => $this->generateUrl('app_organisation_index', ['org' => $org->getId()]),
        ]);
    }

    #[Route('/organisation/{id}/invitations', name: 'app_organisation_invite', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function invite(Organisation $org, Request $request, MailerInterface $mailer, ValidatorInterface $validator, #[Autowire('%app.mailer_from%')] string $from, \App\Shared\Security\RateLimits $limits): Response
    {
        $me = $this->managerOf($org, $request);
        if ('regenerate' === $request->request->getString('action')) {
            $org->regenerateInviteCode();
            $this->em->flush();
            $this->addFlash('success', 'Nouveau lien d\'invitation créé : l\'ancien ne fonctionne plus.');

            return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
        }

        $emails = array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim($e)), preg_split('/[\s,;]+/', $request->request->getString('emails')))));
        $valid = array_slice(array_filter($emails, fn ($e) => 0 === \count($validator->validate($e, new Email()))), 0, 200);
        // Anti-spam : une organisation sans licence valide envoie au plus 5 invitations par jour ; avec licence, 100 par jour
        // Sans licence, le plafond est PAR COMPTE (créer plusieurs organisations gratuites ne le contourne pas) ; avec licence, par organisation
        $limiter = $org->isLicenseValid() ? 'org_invite_licensed' : 'org_invite';
        $limitKey = $org->isLicenseValid() ? (string) $org->getId() : (string) $this->user()->getId();
        if ([] !== $valid && !$limits->allow($limiter, $limitKey, \count($valid))) {
            $this->addFlash('error', $org->isLicenseValid()
                ? 'Limite quotidienne d\'invitations atteinte (100 par jour). Réessayez demain, ou partagez le lien d\'invitation.'
                : 'Limite quotidienne d\'invitations atteinte (5 par jour sans places). Partagez le lien d\'invitation, ou choisissez un pack de places.');

            return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
        }
        $url = $this->generateUrl('app_organisation_join', ['code' => $org->getInviteCode()], UrlGeneratorInterface::ABSOLUTE_URL);
        foreach ($valid as $email) {
            $mailer->send((new TemplatedEmail())->from(Address::create($from))->to($email)
                ->subject($me->getUser()->getDisplayName().' vous invite à rejoindre '.$org->getName().' sur KIINI SAWA')
                ->htmlTemplate('organisation/email/invitation.html.twig')
                ->context(['org' => $org, 'inviter' => $me->getUser()->getDisplayName(), 'url' => $url, 'message' => mb_substr(trim($request->request->getString('message')), 0, 1000)]));
        }
        $ignored = \count($emails) - \count($valid);
        $this->addFlash('success', sprintf('%d invitation(s) envoyée(s).', \count($valid)).($ignored ? sprintf(' %d adresse(s) ignorée(s) car invalides.', $ignored) : ''));

        return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
    }

    #[Route('/organisation/{id}/places', name: 'app_organisation_order', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function order(Organisation $org, Request $request): Response
    {
        $me = $this->managerOf($org, $request);
        if (!$me->isOwner()) {
            throw $this->createAccessDeniedException();
        }
        $reference = trim($request->request->getString('reference'));
        if ('' === $reference) {
            $this->addFlash('error', 'Indiquez la référence de votre paiement pour que nous puissions le retrouver.');

            return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
        }
        try {
            $o = $this->orgs->declareOrder($org, $this->user(), $request->request->getInt('seats'), $request->request->getString('currency'), $request->request->getString('method'), $reference);
            $this->addFlash('success', sprintf('Commande de %d places enregistrée (%s). Elle sera vérifiée sous 24 à 48 h ouvrées.', $o->getSeats(), SubscriptionManager::formatAmount($o->getAmount(), $o->getCurrency())));
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'Pack, devise ou moyen de paiement invalide.');
        }

        return $this->redirectToRoute('app_organisation_index', ['org' => $org->getId()]);
    }

    #[Route('/organisation/{id}/recu/{orderId}', name: 'app_organisation_receipt', requirements: ['id' => '\d+', 'orderId' => '\d+'], methods: ['GET'])]
    public function receipt(Organisation $org, int $orderId, PdfFactory $pdf): Response
    {
        $me = $this->orgs->membershipOf($this->user(), $org);
        $order = $this->em->find(SeatOrder::class, $orderId);
        if ((!$me?->isManager() && !$this->isGranted('ROLE_ADMIN')) || !$order || $order->getOrganisation() !== $org || !$order->getReceiptNumber()) {
            throw $this->createNotFoundException();
        }

        return new Response($pdf->render($this->renderView('organisation/receipt.html.twig', [
            'order' => $order,
            'amount' => SubscriptionManager::formatAmount($order->getAmount(), $order->getCurrency()),
        ])), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'recu-'.$order->getReceiptNumber().'.pdf'),
        ]);
    }

    private function managerOf(Organisation $org, Request $request): Membership
    {
        $me = $this->orgs->membershipOf($this->user(), $org);
        if (!$me?->isActive() || !$me->isManager() || !$this->isCsrfTokenValid('org_manage', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        return $me;
    }

    private function memberOf(Organisation $org, int $memberId): Membership
    {
        $member = $this->em->find(Membership::class, $memberId);
        if (!$member || $member->getOrganisation() !== $org || !$member->isActive()) {
            throw $this->createNotFoundException();
        }

        return $member;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
