<?php

namespace App\Compte\Controller\Admin;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use App\Compte\Service\AccountDeleter;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\AccountTokenService;
use App\Compte\Service\MemberFile;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des utilisateurs : recherche, offre, dernière activité, PRO offert, et fiche d'un membre (27/11/2026) :
 * abonnement, paiements, organisation, couple, usage en compteurs ; renvoyer le code, corriger l'adresse, supprimer le
 * compte à sa demande. L'administrateur ne voit pas le contenu des plans ni du journal (confidentialité).
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
        if ('fiche' === $request->request->getString('retour')) {
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->redirectToRoute('admin_users', ['q' => $request->query->getString('q')]);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user, MemberFile $file): Response
    {
        return $this->render('compte/admin/user.html.twig', ['member' => $user, 'file' => $file->build($user)]);
    }

    /** Le membre n'a pas reçu son code de vérification : en envoyer un nouveau (une minute au moins entre deux). */
    #[Route('/{id}/code', name: '_code', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendCode(User $user, Request $request, AccountTokenService $tokens, AccountMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('admin_user', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif ($user->isVerified()) {
            $this->addFlash('info', 'Cette adresse est déjà vérifiée : rien à envoyer.');
        } elseif (!$tokens->canResend($user, AccountToken::VERIFY_EMAIL)) {
            $this->addFlash('error', 'Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un autre.');
        } else {
            $mailer->sendVerificationCode($user, $tokens->createCode($user, AccountToken::VERIFY_EMAIL));
            $this->addFlash('success', 'Un nouveau code a été envoyé à '.$user->getEmail().'.');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    /**
     * Corriger l'adresse d'un membre qui s'est trompé : la nouvelle adresse doit être libre, elle redevient « à vérifier »
     * et reçoit un code (le membre le saisit à sa prochaine connexion).
     */
    #[Route('/{id}/email', name: '_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeEmail(User $user, Request $request, EntityManagerInterface $em, AccountTokenService $tokens, AccountMailer $mailer, LoggerInterface $logger): Response
    {
        $email = mb_strtolower(trim($request->request->getString('email')));
        if (!$this->isCsrfTokenValid('admin_user', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif (!filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $this->addFlash('error', 'Cette adresse e-mail n\'est pas valide.');
        } elseif ($email === mb_strtolower((string) $user->getEmail())) {
            $this->addFlash('info', 'C\'est déjà son adresse.');
        } elseif (null !== $em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $this->addFlash('error', 'Un autre compte utilise déjà cette adresse.');
        } else {
            $old = (string) $user->getEmail();
            $user->setEmail($email)->setIsVerified(false);
            $em->flush();
            $mailer->sendVerificationCode($user, $tokens->createCode($user, AccountToken::VERIFY_EMAIL));
            $logger->info('Adresse d\'un membre corrigée par un administrateur', ['userId' => $user->getId(), 'by' => $this->getUser()?->getUserIdentifier(), 'old' => $old]);
            $this->addFlash('success', 'Adresse corrigée. Un code de vérification est parti vers '.$email.' : le membre le saisira à sa prochaine connexion.');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    /**
     * Supprimer un compte à la demande du membre (droit à l'effacement) : définitif, confirmé en recopiant son adresse.
     * Jamais un administrateur (ni soi-même) depuis cette page.
     */
    #[Route('/{id}/supprimer', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, AccountDeleter $deleter, AccountMailer $mailer, LoggerInterface $logger): Response
    {
        $email = (string) $user->getEmail();
        if (!$this->isCsrfTokenValid('admin_user', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif (\in_array('ROLE_ADMIN', $user->getRoles(), true) || $this->getUser() === $user) {
            $this->addFlash('error', 'Un compte administrateur ne se supprime pas depuis cette page.');
        } elseif (mb_strtolower(trim($request->request->getString('confirm'))) !== mb_strtolower($email)) {
            $this->addFlash('error', 'Pour confirmer, recopiez exactement l\'adresse du membre. Rien n\'a été supprimé.');
        } else {
            $name = $user->getDisplayName();
            $id = $user->getId();
            $deleter->delete($user);
            $mailer->sendAccountDeleted($email, $name);
            $logger->info('Compte supprimé par un administrateur, à la demande du membre', ['userId' => $id, 'by' => $this->getUser()?->getUserIdentifier()]);
            $this->addFlash('success', 'Le compte de '.$name.' et toutes ses données ont été supprimés. Une confirmation lui a été envoyée.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
}
