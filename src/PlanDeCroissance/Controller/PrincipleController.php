<?php

namespace App\PlanDeCroissance\Controller;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\Principle;
use App\PlanDeVie\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Lois & principes » de chacun des quatre plans stratégiques.
 */
class PrincipleController extends AbstractController
{
    /** Principes proposés, cochés ou non par l'utilisateur. */
    public const PROPOSED = [
        'syst-obj' => ['title' => 'Systèmes > Objectifs', 'description' => 'Je ne me concentre pas sur les résultats mais sur les systèmes qui les produisent.'],
        'char-comp' => ['title' => 'Caractère avant compétence', 'description' => 'Ce que je deviens est plus important que ce que je sais faire.'],
        'one-thing' => ['title' => 'Une chose à la fois', 'description' => 'Je fais peu de choses, mais je les fais profondément.'],
    ];

    #[Route('/principes/{pillar}', name: 'app_principles_save', requirements: ['pillar' => 'CROISSANCE|DEVELOPPEMENT|TRANSMISSION|HYGIENE'], methods: ['POST'])]
    public function save(string $pillar, Request $request, EntityManagerInterface $em, \App\Abonnement\Service\Entitlements $entitlements): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        // Chaque pilier est un plan : réservé à PRO comme sa page (Croissance, Développement, Transmission, Hygiène)
        $entitlements->require($user, \App\Abonnement\Service\Entitlements::PLAN_FEATURES[$pillar]);
        if (!$this->isCsrfTokenValid('principles', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

            return $this->redirectToRoute(PlanCatalog::PLANS[$pillar]['route'], ['tab' => 'LAWS']);
        }

        // Les principes n'ont pas de liens : on peut remplacer la liste du pilier
        foreach ($user->getPrinciples() as $pr) {
            if ($pr->getPillar() === $pillar) {
                $em->remove($pr);
            }
        }

        $add = function (string $title, string $description) use ($user, $pillar, $em): void {
            $em->persist((new Principle())->setUser($user)->setTitle($title)->setDescription($description)->setPillar($pillar));
        };
        foreach ($request->request->all('proposed_principles') as $key) {
            if (isset(self::PROPOSED[$key])) {
                $add(self::PROPOSED[$key]['title'], self::PROPOSED[$key]['description']);
            }
        }
        $descriptions = $request->request->all('custom_principle_descriptions');
        foreach ($request->request->all('custom_principle_titles') as $i => $title) {
            if ('' !== trim((string) $title)) {
                $add(mb_substr(trim($title), 0, 255), trim((string) ($descriptions[$i] ?? '')));
            }
        }
        $em->flush();

        return $this->redirectToRoute(PlanCatalog::PLANS[$pillar]['route'], ['tab' => 'LAWS']);
    }
}
