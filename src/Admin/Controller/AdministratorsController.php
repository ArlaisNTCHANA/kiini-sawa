<?php

namespace App\Admin\Controller;

use App\Admin\Entity\AdminAction;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin › Réglages › Administrateurs et journal (28/11/2026) : qui peut administrer KIINI SAWA, en ajouter ou en
 * retirer un, et le journal de ce que chacun a fait. Un administrateur se connecte toujours avec un code reçu par
 * e-mail (connexion en deux étapes) ; on ne se retire pas soi-même, et il en reste toujours au moins un.
 */
#[Route('/admin/administrateurs', name: 'admin_admins')]
#[IsGranted('ROLE_ADMIN')]
class AdministratorsController extends AbstractController
{
    private const CSRF = 'admin_admins';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/admins/index.html.twig', [
            'admins' => $this->admins(),
            'actions' => $this->em->getRepository(AdminAction::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], 150),
        ]);
    }

    /** Donner l'accès à un membre existant, dont l'adresse est vérifiée. */
    #[Route('/ajouter', name: '_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $email = mb_strtolower(trim($request->request->getString('email')));
        $user = '' !== $email ? $this->em->getRepository(User::class)->findOneBy(['email' => $email]) : null;
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif (!$request->request->getBoolean('confirm')) {
            $this->addFlash('error', 'Cochez la case de confirmation : un administrateur voit les membres, les paiements et tous les réglages.');
        } elseif (!$user instanceof User) {
            $this->addFlash('error', 'Aucun compte avec cette adresse. La personne doit d\'abord créer son compte KIINI SAWA.');
        } elseif (!$user->isVerified()) {
            $this->addFlash('error', 'Cette adresse n\'est pas encore vérifiée : la personne doit d\'abord valider son compte.');
        } elseif (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('info', $user->getEmail().' est déjà administrateur.');
        } else {
            $user->setRoles(array_values(array_unique([...array_diff($user->getRoles(), ['ROLE_USER']), 'ROLE_ADMIN'])));
            $this->em->flush();
            $this->addFlash('success', $user->getDisplayName().' ('.$user->getEmail().') est maintenant administrateur.');

            return $this->redirectToRoute('admin_admins');
        }

        return $this->redirectToRoute('admin_admins');
    }

    #[Route('/{id}/retirer', name: '_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif ($this->getUser() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas vous retirer vous-même : demandez à un autre administrateur.');
        } elseif (\count($this->admins()) <= 1) {
            $this->addFlash('error', 'Il doit rester au moins un administrateur.');
        } else {
            $user->setRoles(array_values(array_diff($user->getRoles(), ['ROLE_ADMIN'])));
            $this->em->flush();
            $this->addFlash('success', 'L\'accès administrateur de '.$user->getDisplayName().' ('.$user->getEmail().') a été retiré. Son compte de membre reste intact.');
        }

        return $this->redirectToRoute('admin_admins');
    }

    /** @return list<User> */
    private function admins(): array
    {
        return array_values(array_filter(
            $this->em->getRepository(User::class)->createQueryBuilder('u')->where('u.roles LIKE :r')->setParameter('r', '%ROLE_ADMIN%')->orderBy('u.id')->getQuery()->getResult(),
            fn (User $u) => \in_array('ROLE_ADMIN', $u->getRoles(), true),
        ));
    }
}
