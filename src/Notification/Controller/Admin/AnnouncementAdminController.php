<?php

namespace App\Notification\Controller\Admin;

use App\Compte\Entity\User;
use App\Notification\Entity\Announcement;
use App\Notification\Repository\AnnouncementRepository;
use App\Notification\Service\AnnouncementSender;
use App\Organisation\Entity\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin › Écrire aux membres (27/11/2026) : rédiger une annonce, choisir le public, la relire telle qu'elle arrivera,
 * s'en envoyer un essai, puis l'envoyer (dans l'application, et par e-mail si choisi). Une annonce envoyée ne se modifie
 * plus. Voir AnnouncementSender pour les règles d'envoi.
 */
#[Route('/admin/messages', name: 'admin_announcements')]
#[IsGranted('ROLE_ADMIN')]
class AnnouncementAdminController extends AbstractController
{
    private const CSRF = 'admin_announcement';

    public function __construct(private readonly EntityManagerInterface $em, private readonly AnnouncementSender $sender)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(AnnouncementRepository $announcements): Response
    {
        return $this->render('notification/admin/announcements.html.twig', [
            'announcements' => $announcements->findBy([], ['createdAt' => 'DESC'], 50),
            'organisations' => $this->em->getRepository(Organisation::class)->findBy([], ['name' => 'ASC']),
            'audiences' => Announcement::AUDIENCES,
            'draft' => null,
        ]);
    }

    #[Route('/nouvelle', name: '_new', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->checkCsrf($request);
        $a = (new Announcement())->setAuthor($this->getUser() instanceof User ? $this->getUser() : null);
        if (null !== ($error = $this->fill($a, $request))) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('admin_announcements');
        }
        $this->em->persist($a);
        $this->em->flush();

        return $this->redirectToRoute('admin_announcements_show', ['id' => $a->getId()]);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Announcement $a): Response
    {
        return $this->render('notification/admin/announcement.html.twig', [
            'a' => $a,
            'count' => $a->isDraft() ? $this->sender->count($a) : null,
            'organisation' => $a->getOrganisationId() ? $this->em->find(Organisation::class, $a->getOrganisationId()) : null,
            'organisations' => $this->em->getRepository(Organisation::class)->findBy([], ['name' => 'ASC']),
            'audiences' => Announcement::AUDIENCES,
        ]);
    }

    #[Route('/{id}/modifier', name: '_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Announcement $a, Request $request): Response
    {
        $this->checkCsrf($request);
        if (!$a->isDraft()) {
            $this->addFlash('error', 'Cette annonce est déjà partie : elle ne se modifie plus.');
        } elseif (null !== ($error = $this->fill($a, $request))) {
            $this->addFlash('error', $error);
        } else {
            $this->em->flush();
            $this->addFlash('success', 'Annonce enregistrée.');
        }

        return $this->redirectToRoute('admin_announcements_show', ['id' => $a->getId()]);
    }

    #[Route('/{id}/essai', name: '_test', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function test(Announcement $a, Request $request): Response
    {
        $this->checkCsrf($request);
        $me = $this->getUser();
        if ($me instanceof User) {
            $this->sender->sendTest($a, $me);
            $this->addFlash('success', 'Un essai est parti vers '.$me->getEmail().'. Regardez-le dans votre boîte avant d\'envoyer.');
        }

        return $this->redirectToRoute('admin_announcements_show', ['id' => $a->getId()]);
    }

    /** Lance l'envoi et traite un premier lot ; la page enchaîne les lots suivants (_continue). */
    #[Route('/{id}/envoyer', name: '_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(Announcement $a, Request $request, LoggerInterface $logger): Response
    {
        $this->checkCsrf($request);
        if ($a->isDraft()) {
            $logger->info('Annonce envoyée aux membres', ['announcement' => $a->getId(), 'audience' => $a->getAudience(), 'by' => $this->getUser()?->getUserIdentifier()]);
            $this->sender->sendBatch($a);
        }

        return $this->redirectToRoute('admin_announcements_show', ['id' => $a->getId()]);
    }

    #[Route('/{id}/continuer', name: '_continue', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function continue(Announcement $a, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            return new JsonResponse(['error' => 'La page a expiré.'], 403);
        }
        $done = $a->isSending() ? $this->sender->sendBatch($a) : !$a->isDraft();

        return new JsonResponse(['reached' => $a->getReached(), 'done' => $done]);
    }

    #[Route('/{id}/supprimer', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Announcement $a, Request $request): Response
    {
        $this->checkCsrf($request);
        if ($a->isDraft()) {
            $this->em->remove($a);
            $this->em->flush();
            $this->addFlash('success', 'Brouillon supprimé.');
        }

        return $this->redirectToRoute('admin_announcements');
    }

    /** Lit le formulaire ; renvoie un message d'erreur prêt à afficher, ou null. */
    private function fill(Announcement $a, Request $request): ?string
    {
        $title = trim($request->request->getString('title'));
        $message = trim(str_replace("\r\n", "\n", $request->request->getString('message')));
        $audience = $request->request->getString('audience', 'ALL');
        $url = trim($request->request->getString('actionUrl'));
        $label = trim($request->request->getString('actionLabel'));
        if ('' === $title || '' === $message) {
            return 'Donnez un titre et un message à l\'annonce.';
        }
        if (!isset(Announcement::AUDIENCES[$audience])) {
            return 'Choisissez à qui l\'envoyer.';
        }
        $orgId = 'ORGANISATION' === $audience ? $request->request->getInt('organisation') : null;
        if ('ORGANISATION' === $audience && null === $this->em->find(Organisation::class, (int) $orgId)) {
            return 'Choisissez l\'organisation.';
        }
        if ('' !== $url && !preg_match('#^(/[^/\s][^\s]*|/|https://[^\s]+)$#', $url)) {
            return 'Le lien du bouton doit commencer par / (une page de KIINI SAWA) ou par https://.';
        }
        $a->setTitle(mb_substr($title, 0, 160))->setMessage(mb_substr($message, 0, 5000))->setAudience($audience)->setOrganisationId($orgId)
            ->setActionUrl('' !== $url ? mb_substr($url, 0, 255) : null)->setActionLabel('' !== $url && '' !== $label ? mb_substr($label, 0, 60) : null)
            ->setWithEmail($request->request->getBoolean('withEmail'));

        return null;
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
    }
}
