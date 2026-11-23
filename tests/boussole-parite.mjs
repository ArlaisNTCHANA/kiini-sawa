// Cas de parité de la Boussole 360 : le calcul PHP (src/Boussole/Service/BoussoleEngine.php) doit donner
// exactement le même résultat que le moteur JS (assets/js/boussole-engine.js).
//
//   node tests/boussole-parite.mjs           vérifie que tests/fixtures/boussole-parite.json est à jour
//   node tests/boussole-parite.mjs --ecrire  le régénère (après une modification volontaire du moteur JS)
//
// tests/Boussole/BoussoleParityTest.php relit ce fichier et compare avec le calcul PHP.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const B = require('../assets/js/boussole-engine.js');
const fixturePath = new URL('./fixtures/boussole-parite.json', import.meta.url);

// Générateur pseudo-aléatoire déterministe (mulberry32)
let seed = 360;
function rand() {
  seed |= 0; seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
}
const pick = () => 1 + Math.floor(rand() * 5);

function fromLevels(levels) {
  return B.ITEMS.map((item) => (item.rev ? 6 - levels[item.plan] : levels[item.plan]));
}

const cases = [];
for (const v of [1, 2, 3, 4, 5]) cases.push(new Array(24).fill(v));
// Profils par plan (niveau effectif identique sur les 4 affirmations d'un plan)
for (let n = 0; n < 300; n++) {
  const levels = {};
  for (const k of B.PLAN_ORDER) levels[k] = pick();
  cases.push(fromLevels(levels));
}
// Paires fort / faible pour couvrir toute la matrice des dynamiques
for (const a of B.PLAN_ORDER) {
  for (const b of B.PLAN_ORDER) {
    if (a === b) continue;
    const levels = {};
    for (const k of B.PLAN_ORDER) levels[k] = 4;
    levels[a] = 5; levels[b] = 1;
    cases.push(fromLevels(levels));
  }
}
// Réponses brutes au hasard
for (let n = 0; n < 400; n++) cases.push(Array.from({ length: 24 }, pick));

const hash = (r) => createHash('sha256').update(JSON.stringify(r)).digest('hex');
const fixture = {
  avertissement: 'Généré par node tests/boussole-parite.mjs --ecrire. Ne pas modifier à la main.',
  // Les 40 premiers cas en clair (lisibles en cas d'écart), tous les cas sous forme d'empreinte
  detail: cases.slice(0, 40).map((answers) => ({ answers, result: B.evaluate(answers) })),
  empreintes: cases.map((answers) => ({ answers, sha256: hash(B.evaluate(answers)) })),
};

if (process.argv.includes('--ecrire')) {
  writeFileSync(fixturePath, JSON.stringify(fixture, null, 1) + '\n');
  console.log(`${cases.length} cas écrits dans tests/fixtures/boussole-parite.json`);
} else {
  const current = JSON.parse(readFileSync(fixturePath, 'utf8'));
  assert.deepEqual(current, JSON.parse(JSON.stringify(fixture)), 'Le moteur JS a changé : relancez avec --ecrire puis mettez à jour BoussoleContent.php / BoussoleEngine.php.');
  console.log(`Parité Boussole : ${cases.length} cas à jour.`);
}
