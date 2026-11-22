import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const B = require('../assets/js/boussole-engine.js');

// Helper: construit les 24 réponses brutes pour obtenir les scores cibles par plan (en niveau effectif 1..5)
function makeAnswers(planLevels) {
  return B.ITEMS.map((item) => {
    const eff = planLevels[item.plan] ?? 3;
    return item.rev ? 6 - eff : eff;
  });
}

// 1. Invariants structurels
assert.equal(B.ITEMS.length, 24, 'Doit contenir exactement 24 items');
for (const planKey of B.PLAN_ORDER) {
  const planItems = B.ITEMS.filter((i) => i.plan === planKey);
  assert.equal(planItems.length, 4, `${planKey} doit avoir 4 items`);
  assert.equal(planItems.filter((i) => !i.rev).length, 2, `${planKey} doit avoir 2 items directs`);
  assert.equal(planItems.filter((i) => i.rev).length, 2, `${planKey} doit avoir 2 items inversés`);
}

// Vérifier les 30 combinaisons de la matrice 6x5
let pairCount = 0;
for (const a of B.PLAN_ORDER) {
  for (const b of B.PLAN_ORDER) {
    if (a === b) continue;
    const key = `${a}__${b}`;
    assert.ok(typeof B.PAIR_DYNAMICS[key] === 'string' && B.PAIR_DYNAMICS[key].length > 40, `Paire manquante ou trop courte : ${key}`);
    pairCount++;
  }
}
assert.equal(pairCount, 30, 'La matrice doit contenir exactement 30 combinaisons');

// 2. Les 14 profils limites & contrastés
const tests = [
  ['1. Tout brut à 1 (neutralisation biais -> 50% plat)', () => {
    const r = B.evaluate(new Array(24).fill(1));
    assert.equal(r.coherence, 50);
    assert.equal(r.spread, 0);
    assert.equal(r.showStrongCard, false);
    assert.equal(r.prioLabel, 'Par où commencer');
  }],
  ['2. Tout brut à 3 (50% plat)', () => {
    const r = B.evaluate(new Array(24).fill(3));
    assert.equal(r.coherence, 50);
    assert.equal(r.spread, 0);
    assert.equal(r.showStrongCard, false);
    assert.equal(r.priorityKey, 'VIE');
    assert.match(r.readingMain, /50 %/);
  }],
  ['3. Tout brut à 5 (neutralisation biais -> 50% plat)', () => {
    const r = B.evaluate(new Array(24).fill(5));
    assert.equal(r.coherence, 50);
    assert.equal(r.spread, 0);
    assert.equal(r.showStrongCard, false);
  }],
  ['4. Vrai 0% partout (tout bas)', () => {
    const r = B.evaluate(makeAnswers({ VIE: 1, CROISSANCE: 1, DEVELOPPEMENT: 1, HYGIENE: 1, INTENDANCE: 1, TRANSMISSION: 1 }));
    assert.equal(r.coherence, 0);
    assert.equal(r.spread, 0);
    assert.equal(r.priorityKey, 'HYGIENE');
    assert.equal(r.stepsMode, 'stabilisation');
    assert.equal(r.showStrongCard, false);
  }],
  ['5. Vrai 100% partout (tout haut)', () => {
    const r = B.evaluate(makeAnswers({ VIE: 5, CROISSANCE: 5, DEVELOPPEMENT: 5, HYGIENE: 5, INTENDANCE: 5, TRANSMISSION: 5 }));
    assert.equal(r.coherence, 100);
    assert.equal(r.spread, 0);
    assert.equal(r.priorityKey, 'TRANSMISSION');
    assert.equal(r.stepsMode, 'structuration');
    assert.equal(r.signals.length, 0);
  }],
  ['6. Tout bas (0%) + Vie à 25% (jamais appelé point fort)', () => {
    const r = B.evaluate(makeAnswers({ VIE: 2, CROISSANCE: 1, DEVELOPPEMENT: 1, HYGIENE: 1, INTENDANCE: 1, TRANSMISSION: 1 }));
    assert.equal(r.strongestKey, 'VIE');
    assert.equal(r.scores.VIE, 25);
    assert.equal(r.strongLabel, 'Votre domaine le moins fragilisé');
    assert.equal(r.strongText, B.PLANS.VIE.lowText);
  }],
  ['7. Tout haut (100%) + Hygiène à 75% (jamais appelé frein)', () => {
    const r = B.evaluate(makeAnswers({ VIE: 5, CROISSANCE: 5, DEVELOPPEMENT: 5, HYGIENE: 4, INTENDANCE: 5, TRANSMISSION: 5 }));
    assert.equal(r.priorityKey, 'HYGIENE');
    assert.equal(r.scores.HYGIENE, 75);
    assert.equal(r.prioLabel, 'Votre principale marge de progression');
    assert.equal(r.prioText, B.PLANS.HYGIENE.highText);
    assert.equal(r.stepsMode, 'structuration');
  }],
  ['8. Égalité partielle en bas (Hygiène 25% & Intendance 25%)', () => {
    const r = B.evaluate(makeAnswers({ VIE: 5, CROISSANCE: 4, DEVELOPPEMENT: 4, HYGIENE: 2, INTENDANCE: 2, TRANSMISSION: 3 }));
    assert.deepEqual(r.lowKeys, ['HYGIENE', 'INTENDANCE']);
    assert.match(r.prioTitle, /Hygiène/);
    assert.match(r.prioTitle, /Intendance/);
    assert.match(r.readingSub, /ex æquo/);
  }],
  ['9. Contraste Vie fort × Hygiène faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 5, CROISSANCE: 4, DEVELOPPEMENT: 4, HYGIENE: 1, INTENDANCE: 3, TRANSMISSION: 3 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.VIE__HYGIENE);
    assert.equal(r.stepsMode, 'stabilisation');
    assert.ok(r.signals.length > 0);
  }],
  ['10. Contraste Développement fort × Vie faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 1, CROISSANCE: 3, DEVELOPPEMENT: 5, HYGIENE: 4, INTENDANCE: 4, TRANSMISSION: 3 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.DEVELOPPEMENT__VIE);
  }],
  ['11. Contraste Croissance fort × Intendance faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 4, CROISSANCE: 5, DEVELOPPEMENT: 3, HYGIENE: 4, INTENDANCE: 1, TRANSMISSION: 3 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.CROISSANCE__INTENDANCE);
  }],
  ['12. Contraste Intendance fort × Transmission faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 4, CROISSANCE: 3, DEVELOPPEMENT: 4, HYGIENE: 4, INTENDANCE: 5, TRANSMISSION: 1 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.INTENDANCE__TRANSMISSION);
  }],
  ['13. Contraste Hygiène fort × Développement faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 3, CROISSANCE: 4, DEVELOPPEMENT: 1, HYGIENE: 5, INTENDANCE: 4, TRANSMISSION: 3 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.HYGIENE__DEVELOPPEMENT);
  }],
  ['14. Contraste Transmission fort × Hygiène faible', () => {
    const r = B.evaluate(makeAnswers({ VIE: 4, CROISSANCE: 4, DEVELOPPEMENT: 3, HYGIENE: 1, INTENDANCE: 3, TRANSMISSION: 5 }));
    assert.equal(r.readingMain, B.PAIR_DYNAMICS.TRANSMISSION__HYGIENE);
  }],
  ['15. Meilleur score modéré (63%) vs Intendance basse (31%) : pas de surenchère "cap clair"', () => {
    // 63% = somme 14 (ex. 4+4+3+3), 31% = somme 9 (ex. 2+2+2+3)
    const raw = B.ITEMS.map((item, idx) => {
      let eff = 3;
      if (item.plan === 'VIE') eff = idx < 12 ? 4 : 3;
      if (item.plan === 'INTENDANCE') eff = idx < 18 ? 2 : 3;
      return item.rev ? 6 - eff : eff;
    });
    const r = B.evaluate(raw);
    assert.equal(r.scores.VIE, 63);
    assert.equal(r.scores.INTENDANCE, 31);
    assert.doesNotMatch(r.readingMain, /cap est clair/i);
    assert.match(r.readingMain, /63 %/);
    assert.match(r.readingMain, /31 %/);
  }]
];

for (const [name, run] of tests) {
  run();
  console.log('✓ ' + name);
}
console.log(`\nTOUS LES TESTS PASSÉS (${tests.length} profils + 30 paires + 24 items symétriques).`);
