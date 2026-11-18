#!/usr/bin/env python3
"""Lecture de la Louis Segond 1910 (domaine public) pour vérifier les références et les citations.

Données : var/bible/lsg.json (non versionné, non déployé ; source : midvash/bible-data, voir docs/modules/personnalite.md).
Usage :
  bin/outils/bible.py "Matthieu 1:19-24"        affiche les versets
  bin/outils/bible.py --check "2 Timothée 1:7" "ce n'est pas un esprit de timidité"   vérifie qu'une citation figure bien dans le passage
"""
import json, re, sys, os, unicodedata

ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
DATA = os.path.join(ROOT, 'var', 'bible', 'lsg.json')

# Noms français usuels -> identifiant du fichier
BOOKS = {
 'genèse':'Gen','exode':'Exod','lévitique':'Lev','nombres':'Num','deutéronome':'Deut','josué':'Josh','juges':'Judg','ruth':'Ruth',
 '1 samuel':'1Sam','2 samuel':'2Sam','1 rois':'1Kgs','2 rois':'2Kgs','1 chroniques':'1Chr','2 chroniques':'2Chr','esdras':'Ezra','néhémie':'Neh',
 'esther':'Esth','job':'Job','psaumes':'Ps','psaume':'Ps','proverbes':'Prov','ecclésiaste':'Eccl','cantique des cantiques':'Song','ésaïe':'Isa',
 'jérémie':'Jer','lamentations':'Lam','ézéchiel':'Ezek','daniel':'Dan','osée':'Hos','joël':'Joel','amos':'Amos','abdias':'Obad','jonas':'Jonah',
 'michée':'Mic','nahum':'Nah','habacuc':'Hab','sophonie':'Zeph','aggée':'Hag','zacharie':'Zech','malachie':'Mal',
 'matthieu':'Matt','marc':'Mark','luc':'Luke','jean':'John','actes':'Acts','romains':'Rom','1 corinthiens':'1Cor','2 corinthiens':'2Cor',
 'galates':'Gal','éphésiens':'Eph','philippiens':'Phil','colossiens':'Col','1 thessaloniciens':'1Thess','2 thessaloniciens':'2Thess',
 '1 timothée':'1Tim','2 timothée':'2Tim','tite':'Titus','philémon':'Phlm','hébreux':'Heb','jacques':'Jas','1 pierre':'1Pet','2 pierre':'2Pet',
 '1 jean':'1John','2 jean':'2John','3 jean':'3John','jude':'Jude','apocalypse':'Rev',
}

def load():
    with open(DATA, encoding='utf-8') as f:
        d = json.load(f)
    return {b['book']: b for b in d['books']}

def norm(s):
    s = unicodedata.normalize('NFKD', s.lower())
    s = ''.join(c for c in s if not unicodedata.combining(c))
    return re.sub(r'[^a-z0-9 ]+', ' ', s.replace('’', "'")).split()

def parse(ref):
    m = re.match(r'^\s*((?:[123] )?[^\d:]+?)\s+(\d+)(?::(\d+)(?:-(\d+))?)?\s*$', ref)
    if not m:
        raise SystemExit(f'Référence illisible : {ref!r}')
    name, ch, v1, v2 = m.group(1).strip().lower(), int(m.group(2)), m.group(3), m.group(4)
    if name not in BOOKS:
        raise SystemExit(f'Livre inconnu : {name!r}')
    return BOOKS[name], ch, (int(v1) if v1 else None), (int(v2) if v2 else (int(v1) if v1 else None))

def verses(bible, ref):
    book, ch, v1, v2 = parse(ref)
    chapter = next((c for c in bible[book]['chapters'] if c['chapter'] == ch), None)
    if not chapter:
        raise SystemExit(f'Chapitre introuvable : {ref}')
    out = []
    for v in chapter['verses']:
        n = v.get('number')
        if v1 is None or (v1 <= n <= v2):
            out.append((n, v.get('text', '').replace("''", "'")))
    if not out:
        raise SystemExit(f'Verset introuvable : {ref}')
    return out

if __name__ == '__main__':
    args = sys.argv[1:]
    if not args:
        raise SystemExit(__doc__)
    bible = load()
    if args[0] == '--check':
        ref, quote = args[1], args[2]
        text = ' '.join(norm(' '.join(t for _, t in verses(bible, ref))))
        q = ' '.join(norm(quote))
        print('OK' if q in text else 'ABSENT : la citation ne figure pas dans ' + ref)
        sys.exit(0 if q in text else 1)
    for ref in args:
        for n, t in verses(bible, ref):
            print(f'{n} {t}')
