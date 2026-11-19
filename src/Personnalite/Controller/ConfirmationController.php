<?php

namespace App\Personnalite\Controller;

use App\Compte\Entity\User;
use App\Feedback\Service\FeedbackService;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Service\ConfirmationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Confirmation des résultats DISC et dons spirituels par les proches : lien personnel, formulaire public sans compte.
 */
final class ConfirmationController extends AbstractController
{
    public function __construct(private readonly ConfirmationService $confirmations, private readonly \App\Shared\Security\RateLimits $limits)
    {
    }

    #[Route('/mes-tests/confirmation/{instrument}/{action}', name: 'app_confirmation_toggle', requirements: ['instrument' => 'disc|dons-spirituels', 'action' => 'ouvrir|fermer'], methods: ['POST'])]
    public function toggle(string $instrument, string $action, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        if ($this->isCsrfTokenValid('confirmation_'.$instrument, $request->request->getString('_csrf_token'))) {
            'ouvrir' === $action ? $this->confirmations->open($user, $instrument) : $this->confirmations->close($user, $instrument);
        }
        $back = $request->request->getString('back');

        return $this->redirect((str_starts_with($back, '/mes-tests') ? $back : $this->generateUrl('app_personality_index')).'#regard-proches');
    }

    /** Formulaire public envoyé aux proches. */
    #[Route('/confirmer/{token}', name: 'app_confirmation_form', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET', 'POST'])]
    public function form(string $token, Request $request): Response
    {
        $req = $this->confirmations->findOpen($token) ?? throw $this->createNotFoundException('Cette demande est fermée.');
        $owner = $req->getUser();
        if ($this->getUser() === $owner) {
            return $this->redirectToRoute('app_personality_index');
        }
        $session = $request->getSession();
        $doneKey = 'confirmation_done_'.$token;
        $errors = [];
        $data = ['relationship' => '', 'most' => '', 'least' => '', 'gifts' => [], 'name' => ''];
        if ($request->isMethod('POST')) {
            // Anti-flood de ce formulaire public : par adresse et lien, puis par adresse (tous liens confondus)
            $ip = $request->getClientIp() ?? 'inconnue';
            $this->limits->require('public_form', $ip.'|'.$token);
            $this->limits->require('public_form_ip', $ip);
            $p = $request->request;
            if ('' !== $p->getString('website')) {
                return $this->render('personnalite/confirmation/thanks.html.twig', ['firstName' => $owner->getDisplayName()]);
            }
            $data = ['relationship' => $p->getString('relationship'), 'most' => $p->getString('most'), 'least' => $p->getString('least'),
                'gifts' => $p->all('gifts'), 'name' => $p->getString('name')];
            if (!$this->isCsrfTokenValid('confirmation_form', $p->getString('_csrf_token'))) {
                $errors[] = 'La page a expiré, merci de réessayer.';
            } elseif ($session->get($doneKey)) {
                $errors[] = 'Vous avez déjà répondu. Merci !';
            } else {
                $errors = $this->confirmations->submit($req, $data);
            }
            if (!$errors) {
                $session->set($doneKey, true);

                return $this->render('personnalite/confirmation/thanks.html.twig', ['firstName' => $owner->getDisplayName()]);
            }
        }

        return $this->render('personnalite/confirmation/form.html.twig', [
            'firstName' => $owner->getDisplayName(), 'req' => $req, 'data' => $data, 'errors' => $errors, 'alreadyDone' => (bool) $session->get($doneKey),
            'relationships' => FeedbackService::RELATIONSHIPS, 'choices' => ConfirmationService::discChoices($token),
            'gifts' => Content::get('gifts.gifts'), 'maxGifts' => ConfirmationService::MAX_GIFTS,
        ], new Response(status: $errors ? 422 : 200));
    }
}
