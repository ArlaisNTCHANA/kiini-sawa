# Architecture & Sécurité des API (Mobile)

> ⚠️ **État au 16/11/2026** : cette page décrit l'API **prévue** pour l'application mobile (phase 7). Elle n'existe pas encore : pas de JWT, pas de NelmioApiDoc, pas de `/api/sync`. Les routes `/api/…` actuelles sont internes aux pages web (session). Voir `AGENTS.md` §8.


Toutes les interactions avec l'application mobile passent par des points d'accès (endpoints) API REST retournant du JSON.

---

## 1. Documentation de l'API (Swagger UI)

La documentation de l'API est générée automatiquement à l'adresse `/api/docs` grâce à **NelmioApiDocBundle** et aux annotations/attributs PHP OpenAPI dans les contrôleurs.

---

## 2. Standards de Conception des API

* **Namespace** : `App\Controller\Api\`
* **Format de réponse** : Toujours retourner un objet JSON structuré de façon homogène.
* **Codes d'état HTTP** :
  * `200 OK` : Succès de la requête.
  * `201 Created` : Entité créée avec succès.
  * `400 Bad Request` : Erreur de validation des données entrantes.
  * `401 Unauthorized` : Authentification requise ou expirée.
  * `403 Forbidden` : Droits insuffisants.
  * `442 Unprocessable Entity` : Erreurs de contraintes d'intégrité ou d'affaires.

---

## 3. Sécurité (JWT / Token)

L'application mobile doit :
1. Envoyer ses identifiants vers `/api/login` pour obtenir un jeton.
2. Inclure ce jeton dans l'en-tête de chaque requête suivante :
   ```http
   Authorization: Bearer <votre_jeton_jwt>
   ```
