<?php

namespace App\Media\Controller;

use App\Compte\Entity\User;
use App\Media\Repository\ReferralRepository;
use App\Media\Service\ReferralService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Parrainage : lien personnel à partager et page « Inviter mes proches ». */
class ReferralController extends AbstractController
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    /** Lien court partagé par un membre : retient le parrain puis ouvre la page d'accueil (provenance « parrainage »). */
    #[Route('/i/{code}', name: 'app_referral_link', requirements: ['code' => '[A-Za-z0-9]{5,16}'], methods: ['GET'])]
    public function link(string $code, Request $request): Response
    {
        $response = $this->redirectToRoute('app_home', ['utm_source' => 'parrainage', 'utm_medium' => 'lien', 'utm_campaign' => 'parrainage']);
        if (null === $this->getUser() && null !== $this->referrals->referrerFromCode($code)) {
            $this->referrals->rememberCode($code, $request, $response);
        }

        return $response;
    }

    #[Route('/parrainage', name: 'app_referral', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ReferralRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $url = $this->generateUrl('app_referral_link', ['code' => $this->referrals->codeFor($user)], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('media/referral.html.twig', [
            'url' => $url,
            'stats' => $this->referrals->stats($user),
            'referrals' => $repository->forReferrer($user),
            'maxPerYear' => ReferralService::MAX_REWARDS_PER_YEAR,
        ]);
    }
}
