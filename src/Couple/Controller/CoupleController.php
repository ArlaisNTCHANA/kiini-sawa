<?php

namespace App\Couple\Controller;

use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleReview;
use App\Couple\Service\CoupleService;
use App\Organisation\Service\PendingInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Espace couple : invitation du conjoint, vision commune, valeurs, projets, thèmes de croissance,
 * rituels, revue de couple et rapport croisé des profils.
 */
class CoupleController extends AbstractController
{
    public function __construct(private readonly CoupleService $couples, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/couple', name: 'app_couple', methods: ['GET'])]
    public function index(SubscriptionManager $prices): Response
    {
        $user = $this->user();
        $couple = $this->couples->coupleOf($user);
        $duo = ['EUR' => $prices->price('DUO', 'EUR'), 'XAF' => $prices->price('DUO', 'XAF'), 'twoProEur' => 2 * $prices->price('YEARLY', 'EUR'), 'twoProXaf' => 2 * $prices->price('YEARLY', 'XAF')];

        if (!$couple) {
            return $this->render('couple/start.html.twig', ['duo' => $duo, 'themes' => CoupleService::THEMES]);
        }
        if (!$couple->isActive()) {
            return $this->render('couple/pending.html.twig', ['couple' => $couple, 'inviteUrl' => $this->inviteUrl($couple)]);
        }
        if (!$this->couples->canUse($couple)) {
            return $this->render('couple/locked.html.twig', ['couple' => $couple, 'partner' => $couple->partnerOf($user), 'duo' => $duo]);
        }

        $month = new \DateTimeImmutable('first day of this month midnight');

        return $this->render('couple/dashboard.html.twig', [
            'couple' => $couple,
            'partner' => $couple->partnerOf($user),
            'themes' => CoupleService::THEMES,
            'categories' => CoupleService::PROJECT_CATEGORIES,
            'frequencies' => CoupleService::RITUAL_FREQUENCIES,
            'review' => $this->couples->reviewFor($couple, $month),
            'reviews' => $this->couples->reviews($couple),
            'questions' => CoupleReview::QUESTIONS,
            'month' => $month,
            'sharesTests' => $couple->sharesTests($user),
        ]);
    }

    #[Route('/couple/creer', name: 'app_couple_create', methods: ['POST'])]
    public function create(Request $request, MailerInterface $mailer, ValidatorInterface $validator, #[Autowire('%app.mailer_from%')] string $from, \App\Shared\Security\RateLimits $limits): Response
    {
        if (!$this->isCsrfTokenValid('couple', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('app_couple');
        }
        if (!$limits->allow('couple_create', (string) $this->user()->getId())) {
            $this->addFlash('error', 'Trop de tentatives. Réessayez dans une heure.');

            return $this->redirectToRoute('app_couple');
        }
        $email = mb_strtolower(trim($request->request->getString('email')));
        if ('' !== $email && \count($validator->validate($email, new Email())) > 0) {
            $this->addFlash('error', 'Adresse email invalide.');

            return $this->redirectToRoute('app_couple');
        }
        if ($email === $this->user()->getEmail()) {
            $this->addFlash('error', 'Indiquez l\'adresse de votre conjoint, pas la vôtre.');

            return $this->redirectToRoute('app_couple');
        }
        try {
            $couple = $this->couples->create($this->user(), $email ?: null);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_couple');
        }
        if ($email) {
            $mailer->send((new TemplatedEmail())->from(Address::create($from))->to($email)
                ->subject($this->user()->getDisplayName().' vous invite à bâtir votre couple sur KIINI SAWA')
                ->htmlTemplate('couple/email/invitation.html.twig')
                ->context(['inviter' => $this->user()->getDisplayName(), 'url' => $this->inviteUrl($couple)]));
            $this->addFlash('success', 'Invitation envoyée à '.$email.'.');
        }

        return $this->redirectToRoute('app_couple');
    }

    /** Lien d'invitation du conjoint : public, pour accueillir aussi un conjoint sans compte. */
    #[Route('/couple/rejoindre/{code}', name: 'app_couple_join', requirements: ['code' => '[a-f0-9]{16}'], methods: ['GET', 'POST'])]
    public function join(string $code, Request $request): Response
    {
        $couple = $this->em->getRepository(Couple::class)->findOneBy(['inviteCode' => $code]);
        if (!$couple || $couple->isActive()) {
            throw $this->createNotFoundException('Cette invitation n\'est plus valable.');
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set(PendingInvitation::COUPLE_KEY, $code);

            return $this->render('couple/join_anonymous.html.twig', ['couple' => $couple]);
        }
        $request->getSession()->remove(PendingInvitation::COUPLE_KEY);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('couple_join', $request->request->getString('_csrf_token'))) {
            try {
                $this->couples->join($user, $couple, $request->request->getBoolean('shareTests'));
                $this->addFlash('success', 'Bienvenue dans votre espace couple avec '.$couple->getPartnerA()->getDisplayName().' !');

                return $this->redirectToRoute('app_couple');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('couple/join.html.twig', ['couple' => $couple]);
    }

    #[Route('/couple/vision', name: 'app_couple_vision', methods: ['POST'])]
    public function vision(Request $request): Response
    {
        $couple = $this->activeCouple($request);
        $couple->setVision(mb_substr(trim($request->request->getString('vision')), 0, 5000) ?: null);
        $couple->setVerse(mb_substr(trim($request->request->getString('verse')), 0, 500) ?: null);
        $this->em->flush();
        $this->addFlash('success', 'Votre vision commune est enregistrée.');

        return $this->redirectToRoute('app_couple', ['_fragment' => 'vision']);
    }

    #[Route('/couple/elements', name: 'app_couple_item_add', methods: ['POST'])]
    public function addItem(Request $request): Response
    {
        $couple = $this->activeCouple($request);
        $kind = $request->request->getString('kind');
        try {
            $this->couples->addItem($couple, $this->user(), $kind, $request->request->all());
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple', ['_fragment' => strtolower($kind)]);
    }

    #[Route('/couple/elements/{id}', name: 'app_couple_item_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateItem(CoupleItem $item, Request $request): Response
    {
        $couple = $this->activeCouple($request);
        if ($item->getCouple() !== $couple) {
            throw $this->createNotFoundException();
        }
        if ('delete' === $request->request->getString('action')) {
            $this->em->remove($item);
            $this->em->flush();
        } else {
            $this->couples->updateItem($item, $request->request->all());
        }

        return $this->redirectToRoute('app_couple', ['_fragment' => strtolower($item->getKind())]);
    }

    #[Route('/couple/revue', name: 'app_couple_review', methods: ['POST'])]
    public function review(Request $request): Response
    {
        $couple = $this->activeCouple($request);
        $this->couples->saveReview($couple, $this->user(), $request->request->all('answers'));
        $this->addFlash('success', 'Revue de couple enregistrée.');

        return $this->redirectToRoute('app_couple', ['_fragment' => 'revue']);
    }

    #[Route('/couple/rapport', name: 'app_couple_report', methods: ['GET'])]
    public function report(): Response
    {
        $couple = $this->couples->coupleOf($this->user());
        if (!$couple || !$this->couples->canUse($couple)) {
            return $this->redirectToRoute('app_couple');
        }

        return $this->render('couple/report.html.twig', ['couple' => $couple, 'report' => $this->couples->crossReport($couple), 'me' => $this->user()]);
    }

    /** Partage des tests, annulation d'invitation ou départ de l'espace couple. */
    #[Route('/couple/reglages', name: 'app_couple_settings', methods: ['POST'])]
    public function settings(Request $request): Response
    {
        $couple = $this->couples->coupleOf($this->user());
        if (!$couple || !$this->isCsrfTokenValid('couple', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('app_couple');
        }
        $action = $request->request->getString('action');
        if ('share' === $action && $couple->isActive()) {
            $couple->setSharesTests($this->user(), $request->request->getBoolean('shareTests'));
            $this->em->flush();
            $this->addFlash('success', 'Préférence de partage enregistrée.');
        } elseif ('dissolve' === $action) {
            $partner = $couple->partnerOf($this->user());
            $this->couples->dissolve($couple);
            $this->addFlash('success', $partner ? 'L\'espace couple a été fermé. Vos espaces personnels restent intacts.' : 'Invitation annulée.');
        }

        return $this->redirectToRoute('app_couple');
    }

    private function activeCouple(Request $request): Couple
    {
        $couple = $this->couples->coupleOf($this->user());
        if (!$couple || !$this->couples->canUse($couple) || !$this->isCsrfTokenValid('couple', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        return $couple;
    }

    private function inviteUrl(Couple $couple): string
    {
        return $this->generateUrl('app_couple_join', ['code' => $couple->getInviteCode()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
