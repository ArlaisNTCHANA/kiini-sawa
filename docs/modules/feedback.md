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
  - `/avis/{jeton}` (**refonte du 24/11/2026**, souvent le premier contact de la personne avec KIINI SAWA) :
    1. **Accueil** (`fb-hero`) : « [prénom] vous demande d'être son témoin » (pas « choisi(e) » : tournure neutre),
       repères (3 minutes, anonymat ou prénom demandé, date limite), une seule action « Commencer » (ancre
       `#questionnaire`), KIINI SAWA en une phrase ;
    2. **Pourquoi votre regard compte** : citations du livre mot pour mot (chapitre 12 « Personne n'a une vue à
       360 degrés… », chapitre 23 « une ressource gratuite… ») ;
    3. **Les trois témoins** : le schéma commun `feedback/_temoins.html.twig` (mode `public`), ouvert, onglet
       « Le témoignage extérieur » sélectionné, « Votre regard » sous le cercle ;
    4. **Comment ça se passe** : les 4 étapes, le temps (deux réponses obligatoires : une qualité, ce que la personne
       fait de mieux), la confidentialité (détail visible à partir de 3 réponses sauf prénom demandé), ce que
       la personne en retire (Boussole 360 gratuite et sans compte) ;
    5. **Questionnaire en 4 étapes** (`feedbackForm()`, Alpine, dans le gabarit) : vous connaissez [prénom] par…
       (boutons radio), qualités (1 à 5 parmi 24 **ou ajoutées avec ses propres mots**, `otherQualities[]`, 3 au plus),
       « en un mot » et ce que [prénom] n'est pas (facultatifs, JSON `{"mot", "pas"}` dans `community_feedback.answers`)
       + ce que [prénom] fait de mieux (obligatoire), point de croissance (facultatif) et prénom. Barre de progression
       « Étape n / 4 » collée en haut, « Continuer » / « Retour », message doux si une réponse manque ; après une erreur
       du serveur, retour à l'étape 4 avec les réponses conservées. **Sans JavaScript**, les 4 étapes s'affichent et
       le formulaire s'envoie normalement.
    En bas, présentation repliée « KIINI SAWA, qu'est-ce que c'est ? ». Champ piège anti-robot, CSRF, limites de
    fréquence (`public_form`, `public_form_ip`) avant tout traitement d'un envoi, 50 réponses au plus.
  - Après l'envoi : **redirection** vers `/avis/{jeton}/merci` (sans elle, Turbo laissait le formulaire affiché). Merci,
    citation du chapitre 12 (« Choisis des personnes qui t'aiment assez… »), puis invitation sans forcer
    (`_discover.html.twig`, aussi utilisée après une confirmation de test) : **action principale « Faire ma Boussole 360
    gratuite »** (`/boussole`, sans compte), puis « Créer mon compte gratuit » et « Découvrir KIINI SAWA », provenance
    `utm_source=feedback`. Revenir sur le lien après avoir répondu mène à cette page.
  - Lien expiré ou fermé : page « Ce lien n'accepte plus de réponses » (410) qui explique la durée d'une semaine ; lien
    inconnu : 404 ; avec la même invitation (`utm_content=lien-termine`).
  - **Aperçu du lien** (WhatsApp, Facebook…) : balises Open Graph (`_share_meta.html.twig`) « [prénom] vous demande
    d'être son témoin » / « 3 minutes, bienveillantes et confidentielles… » + image
    `assets/images/partage/feedback-nourricier.jpg` (1200 × 630, source `docs/media/apercu-feedback-nourricier.html`).
  - Textes modifiables dans Admin › Textes du site (`feedback.public.*`, `feedback.merci.*`, `feedback.ferme.*`,
    `feedback.decouvrir.*`, `feedback.apercu.*`) ; les citations du livre sont fixes et vérifiées par
    `FeedbackTest::testGuestPagesQuoteTheBookWordForWord`.
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
Tests : `tests/Feedback/FeedbackTest.php` (page des proches : `testGuestPageWelcomesExplainsAndInvites`), `tests/Mobile/MobileFeedbackTest.php`.
