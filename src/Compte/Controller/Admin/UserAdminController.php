<?php

namespace App\Compte\Controller\Admin;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des utilisateurs : recherche, offre, dernière activité, PRO offert.
 * L'administrateur ne voit pas le contenu des plans ni du journal (confidentialité).
 */
#[Route('/admin/utilisateurs', name: 'admin_users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, Entitlements $entitlements): Response
    {
        $q = trim($request->query->getString('q'));
        $qb = $em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.subscription', 's')->addSelect('s')
            ->leftJoin('u.userProfile', 'p')->addSelect('p')
            ->orderBy('u.id', 'DESC')->setMaxResults(100);
        if ('' !== $q) {
            $qb->where('u.email LIKE :q OR p.firstName LIKE :q OR p.lastName LIKE :q')->setParameter('q', '%'.$q.'%');
        }
        $users = $qb->getQuery()->getResult();

        return $this->render('compte/admin/users.html.twig', [
            'users' => array_map(fn (User $u) => [
                'user' => $u,
                'isPro' => $entitlements->isPro($u),
                'trialing' => $entitlements->isTrialing($u),
                'until' => $entitlements->proUntil($u),
            ], $users),
            'q' => $q,
        ]);
    }

    #[Route('/{id}/offrir', name: '_gift', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function gift(User $user, Request $request, SubscriptionManager $manager): Response
    {
        $months = max(1, min(36, $request->request->getInt('months', 1)));
        if ($this->isCsrfTokenValid('admin_user', $request->request->getString('_csrf_token'))) {
            $manager->gift($user, $months, trim($request->request->getString('note')) ?: null);
            $this->addFlash('success', sprintf('%d mois de PRO offerts à %s.', $months, $user->getEmail()));
        }

        return $this->redirectToRoute('admin_users', ['q' => $request->query->getString('q')]);
    }
}
