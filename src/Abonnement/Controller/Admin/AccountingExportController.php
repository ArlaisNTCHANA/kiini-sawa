<?php

namespace App\Abonnement\Controller\Admin;

use App\Abonnement\Service\AccountingExport;
use App\Admin\EventSubscriber\AdminActionRecorder;
use App\Compte\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin › Les chiffres › Exports pour la comptabilité (28/11/2026) : paiements, commandes de places et membres en
 * tableur. Chaque téléchargement est noté au journal des actions (la liste des membres contient des données personnelles).
 */
#[Route('/admin/exports', name: 'admin_exports')]
#[IsGranted('ROLE_ADMIN')]
class AccountingExportController extends AbstractController
{
    private const KINDS = ['paiements' => 'Paiements des membres', 'places' => 'Commandes de places des organisations', 'membres' => 'Liste des membres'];

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $year = (int) date('Y');

        return $this->render('abonnement/admin/exports.html.twig', ['years' => range($year, max(2026, $year - 4)), 'kinds' => self::KINDS]);
    }

    #[Route('/{kind}', name: '_download', requirements: ['kind' => 'paiements|places|membres'], methods: ['POST'])]
    public function download(string $kind, Request $request, AccountingExport $export, AdminActionRecorder $journal): Response
    {
        if (!$this->isCsrfTokenValid('admin_exports', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('admin_exports');
        }
        [$from, $to, $period] = $this->period($request);
        $rows = match ($kind) {
            'paiements' => $export->payments($from, $to),
            'places' => $export->seatOrders($from, $to),
            default => $export->members(),
        };
        $name = 'kiini-sawa-'.$kind.('membres' === $kind ? '-'.date('Y-m-d') : '-'.$period).'.csv';
        $me = $this->getUser();
        if ($me instanceof User) {
            $journal->write($me, 'admin_exports_download', 'Export téléchargé : '.mb_strtolower(self::KINDS[$kind]).('membres' === $kind ? '' : ' ('.str_replace('-', '/', $period).')').', '.(\count($rows) - 1).' ligne(s).');
        }

        return new Response(AccountingExport::csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: string} début, fin (exclue), libellé de période */
    private function period(Request $request): array
    {
        $year = max(2020, min(2100, $request->request->getInt('annee', (int) date('Y'))));
        $month = $request->request->getInt('mois');
        if ($month >= 1 && $month <= 12) {
            $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));

            return [$from, $from->modify('+1 month'), sprintf('%02d-%d', $month, $year)];
        }
        $from = new \DateTimeImmutable($year.'-01-01');

        return [$from, $from->modify('+1 year'), (string) $year];
    }
}
