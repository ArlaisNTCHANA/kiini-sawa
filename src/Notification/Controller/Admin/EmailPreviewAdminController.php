<?php

namespace App\Notification\Controller\Admin;

use App\Compte\Entity\User;
use App\Notification\Service\EmailCatalog;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin › Réglages › Aperçu des e-mails (27/11/2026) : chaque e-mail de l'application tel qu'un membre le reçoit (vrai
 * gabarit, données d'exemple), et « M'envoyer cet e-mail » pour le voir dans sa propre boîte. Rien n'est envoyé à un
 * membre depuis cette page.
 */
#[Route('/admin/emails', name: 'admin_emails')]
#[IsGranted('ROLE_ADMIN')]
class EmailPreviewAdminController extends AbstractController
{
    public function __construct(private readonly EmailCatalog $catalog)
    {
    }

    #[Route('/{key}', name: '', requirements: ['key' => '[a-z0-9_]*'], defaults: ['key' => ''], methods: ['GET'])]
    public function index(string $key): Response
    {
        $all = $this->catalog->all();
        $key = isset($all[$key]) ? $key : (string) array_key_first($all);
        $email = $all[$key];

        return $this->render('notification/admin/emails.html.twig', [
            'all' => $all, 'groups' => EmailCatalog::GROUPS, 'key' => $key, 'email' => $email,
            'html' => $this->renderView($email['template'], $email['context']),
        ]);
    }

    #[Route('/{key}/essai', name: '_test', requirements: ['key' => '[a-z0-9_]+'], methods: ['POST'])]
    public function test(string $key, Request $request, MailerInterface $mailer, #[Autowire('%app.mailer_from%')] string $from): Response
    {
        $all = $this->catalog->all();
        $me = $this->getUser();
        if (!isset($all[$key]) || !$me instanceof User || !$this->isCsrfTokenValid('admin_emails', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('admin_emails');
        }
        $email = $all[$key];
        $mailer->send((new TemplatedEmail())->from(Address::create($from))->to((string) $me->getEmail())
            ->subject('[Essai] '.$email['subject'])->htmlTemplate($email['template'])->context($email['context']));
        $this->addFlash('success', 'Envoyé à '.$me->getEmail().' : regardez-le dans votre boîte (et sur votre téléphone).');

        return $this->redirectToRoute('admin_emails', ['key' => $key]);
    }
}
