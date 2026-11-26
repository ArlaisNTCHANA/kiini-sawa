<?php

namespace App\Organisation\Controller\Admin;

use App\Compte\Entity\User;
use App\Organisation\Entity\Organisation;
use App\Organisation\Entity\SeatOrder;
use App\Organisation\Service\OrganisationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des organisations : création pour un pilote, places et validité réglées à la main,
 * validation des commandes de places.
 */
#[IsGranted('ROLE_ADMIN')]
class OrganisationAdminController extends AbstractController
{
    #[Route('/admin/organisations', name: 'admin_organisations', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('organisation/admin/index.html.twig', [
            'organisations' => $em->getRepository(Organisation::class)->findBy([], ['createdAt' => 'DESC']),
            'types' => Organisation::TYPES,
        ]);
    }

    #[Route('/admin/organisations/creer', name: 'admin_organisations_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, OrganisationService $orgs): Response
    {
        if (!$this->isCsrfTokenValid('admin_org', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('admin_organisations');
        }
        $owner = $em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($request->request->getString('ownerEmail')))]);
        if (!$owner) {
            $this->addFlash('error', 'Aucun compte avec cet e-mail : le responsable doit d\'abord créer son compte.');

            return $this->redirectToRoute('admin_organisations');
        }
        $org = $orgs->create($owner, $request->request->getString('name'), $request->request->getString('type'));
        $this->applyLicense($org, $request);
        $em->flush();
        $this->addFlash('success', $org->getName().' créée, responsable : '.$owner->getEmail().'.');

        return $this->redirectToRoute('admin_organisations');
    }

    #[Route('/admin/organisations/{id}/licence', name: 'admin_organisations_license', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function license(Organisation $org, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_org', $request->request->getString('_csrf_token'))) {
            $this->applyLicense($org, $request);
            $em->flush();
            $this->addFlash('success', 'Licence de '.$org->getName().' mise à jour.');
        }

        return $this->redirectToRoute('admin_organisations');
    }

    #[Route('/admin/commandes-places/{id}/{decision}', name: 'admin_seat_order', requirements: ['id' => '\d+', 'decision' => 'valider|refuser'], methods: ['POST'])]
    public function decide(SeatOrder $order, string $decision, Request $request, OrganisationService $orgs): Response
    {
        if ($this->isCsrfTokenValid('admin_payment', $request->request->getString('_csrf_token'))) {
            if ('valider' === $decision) {
                $orgs->validateOrder($order, trim($request->request->getString('note')) ?: null);
                $this->addFlash('success', 'Places activées pour '.$order->getOrganisation()->getName().'.');
            } elseif ('' !== $reason = trim($request->request->getString('reason'))) {
                $orgs->rejectOrder($order, $reason);
                $this->addFlash('success', 'Commande refusée, le client a été prévenu.');
            } else {
                $this->addFlash('error', 'Indiquez le motif du refus.');
            }
        }

        return $this->redirectToRoute('admin_payments');
    }

    private function applyLicense(Organisation $org, Request $request): void
    {
        $org->setSeats($request->request->getInt('seats'));
        $until = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('validUntil'));
        $org->setSeatsValidUntil($until ?: null);
    }
}
