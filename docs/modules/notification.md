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
