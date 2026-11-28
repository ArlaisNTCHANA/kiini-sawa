<?php

namespace App\Admin\EventSubscriber;

use App\Compte\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Journal des actions d'administration (Admin › Réglages › Administrateurs et journal, 27/11/2026).
 *
 * Pour chaque envoi de formulaire réussi dans /admin (POST suivi d'une redirection, avec un message de réussite), on
 * garde : l'administrateur, la date, le message de réussite tel qu'il l'a lu (« Paiement validé : PRO activé… »), et
 * ce qui était concerné (le membre, le paiement…). Rien d'autre du formulaire n'est gardé. Aucune page à modifier :
 * toute nouvelle action d'administration est tracée d'elle-même.
 */
final class AdminActionRecorder
{
    public function __construct(private readonly Security $security, private readonly Connection $db)
    {
    }

    /** Messages de réussite déjà en attente avant l'action : ils ne sont pas les siens. */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: -50)]
    public function before(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && $request->isMethod('POST') && str_starts_with($request->getPathInfo(), '/admin') && $request->hasSession()
            && ($session = $request->getSession()) instanceof FlashBagAwareSessionInterface) {
            $request->attributes->set('_admin_success_before', \count($session->getFlashBag()->peek('success')));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -50)]
    public function after(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST') || !str_starts_with($request->getPathInfo(), '/admin')
            || !$event->getResponse()->isRedirection() || !$request->hasSession()) {
            return;
        }
        $session = $request->getSession();
        $user = $this->security->getUser();
        if (!$session instanceof FlashBagAwareSessionInterface || !$user instanceof User || !\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }
        $success = \array_slice($session->getFlashBag()->peek('success'), (int) $request->attributes->get('_admin_success_before', 0));
        if ([] === $success) {
            return; // action refusée ou incomplète : rien à garder
        }
        $route = (string) $request->attributes->get('_route');
        $this->write($user, $route, (string) end($success), $this->target($route, $request->attributes->get('_route_params', [])));
    }

    /** Écrit une ligne au journal ; aussi pour une action sans redirection (un téléchargement, par exemple). */
    public function write(User $user, string $route, string $message, ?string $target = null): void
    {
        try {
            $this->db->insert('admin_action', [
                'actor_id' => $user->getId(),
                'actor_email' => mb_substr((string) $user->getEmail(), 0, 180),
                'route' => mb_substr($route, 0, 80),
                'message' => mb_substr($message, 0, 255),
                'target' => null !== $target ? mb_substr($target, 0, 180) : null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // le journal ne doit jamais empêcher une action d'aboutir
        }
    }

    /** Ce qui était concerné, lisible : l'adresse du membre, le numéro du paiement, etc. */
    private function target(string $route, array $params): ?string
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        if (null === $id) {
            return null;
        }
        $email = fn (string $sql) => ($v = $this->db->fetchOne($sql, ['id' => $id])) ? (string) $v : null;

        return match (true) {
            str_starts_with($route, 'admin_users'), str_starts_with($route, 'admin_admins') => $email('SELECT email FROM `user` WHERE id = :id') ?? 'membre n° '.$id,
            str_starts_with($route, 'admin_payments') => 'paiement n° '.$id.(($m = $email('SELECT u.email FROM `transaction` t JOIN subscription s ON s.id = t.subscription_id JOIN `user` u ON u.id = s.user_id WHERE t.id = :id')) ? ' ('.$m.')' : ''),
            str_starts_with($route, 'admin_seat_order') => 'commande de places n° '.$id,
            str_starts_with($route, 'admin_organisations') => ($n = $email('SELECT name FROM organisation WHERE id = :id')) ?? 'organisation n° '.$id,
            str_starts_with($route, 'admin_announcements') => ($n = $email('SELECT title FROM announcement WHERE id = :id')) ? '« '.mb_substr($n, 0, 150).' »' : 'annonce n° '.$id,
            str_starts_with($route, 'admin_academie') => 'parcours ou leçon n° '.$id,
            default => 'n° '.$id,
        };
    }
}
