# Module Feedback

**Interface utilisateur** : Oui (depuis le 17/11/2026).

## Feedback nourricier (livre : chapitre 12 « Confirmations », les trois témoins ; chapitre 23 « Revues, Feedback & Défis »)

Porte d'entrée vers KIINI SAWA : les proches qui reçoivent le lien découvrent souvent l'application à cette occasion
(refonte du 24/11/2026, demandée par le fondateur).

- **Page du membre** : Mes tests › Feedback nourricier (`/mes-tests/feedback`).
  - Création d'un lien personnel (`FeedbackRequest`), **valable 7 jours** (`expiresAt`), réponses anonymes par défaut ou
    avec prénom obligatoire. Si le profil n'a pas de prénom, il est demandé à la création (sinon les proches verraient le
    début de l'adresse e-mail).
  - **Message prêt à envoyer** en « tu » ou en « vous » (modifiable), boutons « Envoyer sur WhatsApp » (wa.me),
    « Copier le message », « Partager… » (si le navigateur le permet), « Par e-mail », « Copier le lien seul ». Textes des
    messages dans `FeedbackTexts` (Admin › Textes du site, partie « feedback »).
  - **Prolonger d'une semaine** (`FeedbackService::extend`, jamais plus de 21 jours devant soi ; bouton affiché à 3 jours
    de la fin) ; **rouvrir** un lien arrivé à son terme ; une demande **fermée** ne se rouvre pas, on en crée une nouvelle.
  - Les résultats restent visibles après la fin ou la fermeture ; **demandes précédentes** listées
    (`/mes-tests/feedback/demande/{id}`).
  - **Explications** (refonte du 24/11/2026) : schéma interactif **des trois témoins** (`feedback/_temoins.html.twig`) —
    trois cercles qui se recoupent avec la certitude au centre, comme la figure du livre, plus trois onglets (flèches du
    clavier). Définition et exemple = texte du livre mot pour mot (`BookFigures::FIGURES['fig_temoins']`, vérifié par
    `BookFiguresTest`) ; le lien avec KIINI SAWA et le feedback = textes `feedback.temoins.*` (Admin › Textes du site).
    Puis : bien choisir les personnes, ce que vos proches verront, ce que vous recevrez, relire avec bienveillance
    (recevoir, filtrer, croiser, agir ; d'après le chapitre 23), liens vers les chapitres 8, 12 et 23. Première demande :
    explications d'abord, bouton « Créer mon lien » ensuite ; demande existante : lien et résultats d'abord. Tous les
    blocs ont la largeur de la page (`ks-page--narrow`).
- **Pages publiques** (bandeau réduit au nom, sans boutons) :
  - `/avis/{jeton}` : vous connaissez [prénom] par…, 1 à 5 qualités parmi 24 (**des noms** : Générosité, Courage…, justes
    pour tout le monde) **ou ajoutées avec ses propres mots** (`otherQualities[]`, 3 au plus, comptées dans les 5 ; une
    qualité de la liste écrite autrement reprend son nom), **« en un mot » : comment décrire [prénom], et ce que [prénom]
    n'est pas** (facultatifs, 40 caractères, rangés en JSON `{"mot", "pas"}` dans la colonne existante
    `community_feedback.answers`, sans migration), ce que [prénom] fait de mieux (obligatoire), un point de croissance
    (facultatif), prénom. En haut, schéma des trois témoins replié (« votre regard » = le troisième) ; en bas, courte
    présentation repliée « KIINI SAWA, qu'est-ce que c'est ? ».
    Repères : 3 minutes, anonymat, date limite. Champ piège anti-robot, limites de fréquence (`public_form`,
    `public_form_ip`) avant tout traitement d'un envoi, 50 réponses au plus.
  - Après l'envoi : **redirection** vers `/avis/{jeton}/merci` (sans elle, Turbo laissait le formulaire affiché). Merci +
    invitation discrète « Et vous, que verraient vos proches chez vous ? » (`_discover.html.twig`, provenance
    `utm_source=feedback`). Revenir sur le lien après avoir répondu mène à cette page.
  - Lien expiré ou fermé : page « Ce lien n'accepte plus de réponses » (410), lien inconnu : 404, avec la même invitation.
  - **Aperçu du lien** (WhatsApp, Facebook…) : balises Open Graph (`_share_meta.html.twig`) « [prénom] aimerait votre
    regard » + image `assets/images/partage/feedback-nourricier.jpg` (1200 × 630, source `docs/media/apercu-feedback-nourricier.html`).
- **Tournures neutres** : l'application ne connaît pas le sexe des personnes. Ni « il ou elle », ni « (e) » dans ces pages.
  Les anciennes réponses (qualités au masculin) sont converties à l'affichage (`FeedbackService::LEGACY_QUALITIES`).
- Synthèse (`FeedbackService::summary`) : qualités les plus citées, mots « en un mot » (`words`, `notWords` ; même mot
  écrit autrement regroupé, sans tenir compte des majuscules ni des accents), forces, pistes de croissance, dans un ordre mélangé ;
  détail visible seulement à partir de 3 réponses (protection de l'anonymat) sauf si le prénom est obligatoire.
- Notifications (`FEEDBACK_RECEIVED`) : à chaque réponse ; tâche quotidienne (`app:reminders:send` →
  `FeedbackService::runDailyChecks`) : lien arrivé à son terme (avec le nombre de réponses) et rappel unique si moins de
  3 réponses après 3 jours. Sans doublon (clé de notification).
- **Mobile** : écran natif `mobile-v1/app/feedback` (API `/api/mobile/v1/feedback`, voir `docs/api-mobile.md`) : même
  lien, messages, WhatsApp et feuille de partage du téléphone, prolongation, synthèse, demandes passées.

## Avis sur l'application

- « Donner mon avis » (`/donner-mon-avis`), dans le menu du compte et le menu mobile : idée, problème, remarque,
  encouragement ; la page d'origine est jointe au message.
- Admin › Croissance › Avis des utilisateurs (`/admin/avis`) : à traiter / traités, réponse par email.

Entités : `FeedbackRequest`, `CommunityFeedback`, `AppFeedback`. Dormantes : `Feedback` (feedback libre entre membres),
`FeedbackQuestion` (questions configurables ; le formulaire utilise des questions fixes).
Tests : `tests/Feedback/FeedbackTest.php`, `tests/Mobile/MobileFeedbackTest.php`.
