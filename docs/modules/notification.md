# Module Notification

**Interface utilisateur** : Oui (cloche dans la barre du haut, page `/notifications`, préférences dans `/profil#notifications`)

## Rôle

Prévenir l'utilisateur au bon moment, dans l'application et par email, sans le harceler.

## Utilisation dans le code

```php
$notifier->notify($user, NotificationType::CONNECTION_ACCEPTED, 'Titre', 'Message', '/social', 'clé-anti-doublon');
```

`Notifier` applique les préférences de l'utilisateur (type activé, notifications dans l'application,
emails de rappel, emails immédiats) et n'envoie jamais deux fois un rappel portant la même clé.
Chaque email contient un lien signé de désabonnement en un clic (sans connexion) et l'en-tête `List-Unsubscribe`.

## Rappels (`app:reminders:send`)

| Rappel | Quand | Désactivable |
|---|---|---|
| Revue hebdomadaire | Le jour choisi (dimanche par défaut), si la revue de la semaine n'est pas faite | Oui |
| Revue mensuelle | Du 1er au 3 du mois, si la revue du mois précédent n'est pas faite | Oui |
| Rituels oubliés | Aucun rituel quotidien coché depuis 3 jours (au plus une fois par semaine) | Oui |
| Échéances | 7 jours avant l'échéance d'un objectif, axe ou projet | Oui |
| Partenaire inactif | Partenaire sans activité depuis 7 jours (une fois par semaine et par partenaire) | Oui |
| Anniversaire (`BIRTHDAY`, `BirthdayGreeter`) | Le jour de l'anniversaire (29 février fêté le 28 les années non bissextiles) : e-mail + notification + téléphone « Une nouvelle année de vie commence… », lien vers le Plan de Vie ; clé `anniversaire AAAA` | Oui (`notifyBirthday`, lien de désabonnement) |

## Mise en production

```
# crontab : rappels chaque jour à 8 h
0 8 * * * cd /chemin/vers/web && php bin/console app:reminders:send
# worker d'envoi des emails (Messenger, transport async)
php bin/console messenger:consume async --time-limit=3600
```

`--date=AAAA-MM-JJ` simule une date (tests). Relancer la commande le même jour n'envoie rien de plus.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Notification/` (`Service/Notifier`, `Service/ReminderService`, `Service/NotificationMailer`) |
| Templates | `templates/notification/` |
| Tests | `tests/Notification/` |

## Emails d'accueil (16/11/2026)

`Service/OnboardingSequence` : une étape par jour clé après l'inscription, adaptée à ce que l'utilisateur a déjà fait.

| Jour | Email | Envoyé si |
|---|---|---|
| J0 | Bienvenue (fondations, méthode, essai PRO) | dès la vérification de l'email (rattrapage à J1) |
| J1 | Écrire sa vision | la vision est vide |
| J3 | Premier rituel, ou profil complet si un rituel existe | ni rituel, ou pas encore de test |
| J7 | Première revue de la semaine (+ conseil partenaire) | toujours |
| J9 | Les 4 plans stratégiques | essai PRO en cours (le rappel de fin d'essai part à J11) |

Type `NotificationType::ONBOARDING` : email seulement (pas dans le centre de notifications), préférence
« conseils de démarrage » (`notify_onboarding`), lien de désabonnement en un clic. Clé anti-doublon « accueil J<n> ».
Gabarits : `templates/notification/email/onboarding/`. Envoi quotidien via `app:reminders:send` (tâche planifiée).


## Notifications sur le téléphone (17/11/2026)

Web Push (`minishlink/web-push`), clés VAPID dans `.env.prod.local` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`).
Sans clés, rien n'est envoyé et le bouton n'apparaît pas.

- Activation par appareil : profil › Notifications (« Activer sur ce téléphone ») et bannière sur l'accueil dans l'application
  installée. Une notification de test part à l'activation. Sur iPhone : seulement dans l'application installée (iOS 16.4+).
- `PushSubscription` (un enregistrement par appareil), `PushSender` (envoi, suppression des abonnements expirés).
- `Notifier` envoie aussi sur le téléphone les types `isPushable()` : revues, rituels, échéances, défis, partenaires,
  parrainage, feedback, fin d'essai et d'abonnement, paiement validé.
- `DAILY_RITUALS` : rappel du matin « Vos rituels du jour » (tâche planifiée), téléphone uniquement, préférence « rituels ».
- Service worker : événements `push` et `notificationclick` (`public/sw.js`).
