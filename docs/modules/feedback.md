# Module Feedback

**Interface utilisateur** : Oui (depuis le 17/11/2026).

## Feedback nourricier (chapitre 14 du livre)

- Page du membre : Mes tests › Feedback nourricier (`/mes-tests/feedback`) : création d'un lien personnel
  (`FeedbackRequest`), réponses anonymes par défaut ou avec prénom obligatoire, partage WhatsApp / email, fermeture.
- Formulaire public des proches : `/avis/{jeton}` : lien avec la personne, 1 à 5 qualités parmi 24, ce qu'elle fait de
  mieux (obligatoire), un point de croissance (facultatif), prénom (facultatif ou obligatoire). Champ piège anti-robot,
  une réponse par session, 50 réponses au plus. Page de remerciement avec invitation à créer son Plan de Vie (provenance « feedback »).
- Synthèse (`FeedbackService::summary`) : qualités les plus citées, forces, pistes de croissance, dans un ordre mélangé ;
  détail visible seulement à partir de 3 réponses (protection de l'anonymat) sauf si le prénom est obligatoire.
- Le membre est prévenu à chaque réponse (`FEEDBACK_RECEIVED`).

## Avis sur l'application

- « Donner mon avis » (`/donner-mon-avis`), dans le menu du compte et le menu mobile : idée, problème, remarque,
  encouragement ; la page d'origine est jointe au message.
- Admin › Croissance › Avis des utilisateurs (`/admin/avis`) : à traiter / traités, réponse par email.

Entités : `FeedbackRequest`, `CommunityFeedback`, `AppFeedback`. Dormantes : `Feedback` (feedback libre entre membres),
`FeedbackQuestion` (questions configurables ; le formulaire utilise des questions fixes).
Tests : `tests/Feedback/FeedbackTest.php`.
