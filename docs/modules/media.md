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
