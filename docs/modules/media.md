# Module Media

Croissance et acquisition : d'où viennent les inscrits, et (à venir) le pilotage des réseaux sociaux depuis l'administration.

## Provenance des inscrits

- `Service/OriginTracker` : lit les paramètres `utm_source`, `utm_medium`, `utm_campaign`, `utm_content` ou, à défaut,
  le site d'origine (facebook, instagram, tiktok, youtube, whatsapp, google, site vitrine…). Les alias courts sont
  normalisés (`tt` → tiktok, `ig` → instagram, `fb` → facebook, `yt` → youtube, `wa` → whatsapp).
- `EventSubscriber/OriginCookieSubscriber` : pour un visiteur non connecté, pose le cookie propre au site `ks_origine`
  (30 jours). Un lien suivi remplace toujours la valeur ; un simple site d'origine ne remplace rien.
- À l'inscription (`Compte/Controller/RegistrationController`), `OriginTracker::originFor()` crée un `SignupOrigin`
  (invitation de couple ou d'organisation, sinon cookie, sinon requête, sinon « direct ») et efface le cookie.
- Admin : **Croissance › Provenance des inscrits** (`/admin/provenance`) : entonnoir par canal et par campagne
  (inscrits, email vérifié, fondations posées, payants), derniers inscrits, générateur de liens suivis.
- Confidentialité : cookie décrit dans la politique de confidentialité (mesure d'audience exemptée, sans donnée personnelle).

Convention des liens : voir `docs/media/brief-lancement-media.md` (§10).

Tests : `tests/Media/SignupOriginTest.php`.

## Parrainage (17/11/2026)

- Lien personnel `/i/{code}` (code = signature + identifiant, calculé, rien à stocker ; `ReferralService::codeFor()`).
  Le lien pose le cookie `ks_parrain` (30 jours) et ouvre l'accueil avec la provenance « parrainage ».
- À l'inscription : `Referral` créé, essai PRO du filleul porté à 30 jours.
- À la vérification de l'email du filleul : le parrain reçoit 1 mois PRO (`SubscriptionManager::gift`, sans le message
  générique) et l'email `REFERRAL_REWARD` ; 12 récompenses par an au plus ; jamais deux fois.
- Page « Inviter mes proches » (`/parrainage`) : lien, WhatsApp, Facebook, email, invitations et mois gagnés.
- Les cadeaux (montant 0) ne comptent pas comme « payants » dans Provenance des inscrits.

Tests : `tests/Media/ReferralTest.php`.
