<?php

namespace App\Personnalite\Service;

/**
 * Moteur de lecture croisée : il confronte les résultats de plusieurs tests et en tire des « lectures » (tensions, paradoxes,
 * convergences, appels, risques, chemins de croissance). Ce n'est ni un score ni de l'apprentissage automatique : chaque règle
 * est écrite, nommée et vérifiable, et chaque lecture cite ses preuves (quel test dit quoi).
 *
 * Principes (voir docs/modules/personnalite.md) :
 *  - une lecture n'est produite que si les données la justifient (seuils explicites) ;
 *  - une lecture recoupée par plusieurs tests pèse plus qu'une lecture appuyée par un seul ;
 *  - une hypothèse (paradoxe, tension) est présentée comme telle, jamais comme un verdict ;
 *  - chaque lecture se termine par une question à se poser et un premier pas relié à un plan.
 *
 * Entrée : les rapports de chaque test (clés disc, bigfive, type, enneagramme, dons, comme ProfileSynthesis::collect), la
 * convergence des trois axes et, si disponible, l'équilibre de vie. Sortie : lectures classées de la plus importante à la moindre.
 */
final class InsightEngine
{
    public const KINDS = [
        'risk' => ['label' => 'Vigilance', 'color' => '#9A3412', 'bg' => '#FFF7ED'],
        'tension' => ['label' => 'Tension', 'color' => '#854D0E', 'bg' => '#FEF3C7'],
        'paradox' => ['label' => 'Paradoxe', 'color' => '#334155', 'bg' => '#F1F5F9'],
        'convergence' => ['label' => 'Convergence', 'color' => '#166534', 'bg' => '#F0FDF4'],
        'calling' => ['label' => 'Appel', 'color' => '#173D2E', 'bg' => '#DCE7DF'],
        'growth' => ['label' => 'Croissance', 'color' => '#1E3A8A', 'bg' => '#EFF6FF'],
    ];

    /** Types de l'ennéagramme : profils DISC qu'on s'attend d'ordinaire à leur voir exprimer (lecture usuelle, non démontrée). */
    public const ENNEAGRAM_EXPECTS = [1 => ['C'], 2 => ['S', 'I'], 3 => ['D', 'I'], 4 => ['C', 'I'], 5 => ['C'], 6 => ['S', 'C'], 7 => ['I'], 8 => ['D'], 9 => ['S']];

    private const PUBLIC_GIFTS = ['ENSEIGNEMENT', 'EXHORTATION', 'PROPHETIE', 'EVANGELISATION', 'PRESIDENCE'];
    private const HIDDEN_GIFTS = ['SERVICE', 'INTERCESSION', 'MISERICORDE', 'LIBERALITE'];
    private const CARE_GIFTS = ['MISERICORDE', 'EXHORTATION', 'PASTORAT', 'HOSPITALITE'];
    private const CONCRETE_GIFTS = ['SERVICE', 'LIBERALITE', 'HOSPITALITE', 'PRESIDENCE'];
    private const THOUGHT_GIFTS = ['PROPHETIE', 'SAGESSE', 'CONNAISSANCE', 'ENSEIGNEMENT'];


    /**
     * Lectures internes au Big Five : deux facettes d'un même profil qui, ensemble, disent plus que chacune seule.
     * a et b : [facette, niveau attendu]. Ces rapprochements sont des hypothèses de lecture, non des résultats statistiques.
     */
    private const BIGFIVE_PAIRS = [
        ['id' => 'authority-no-crowd', 'kind' => 'tension', 'a' => ['E3', 'high'], 'b' => ['E2', 'low'], 'weight' => 38,
            'title' => 'Une autorité qui n\'a pas besoin de foule',
            'text' => 'Vous savez vous affirmer et prendre la parole quand il le faut, sans chercher la compagnie des groupes. Vous conduisez mieux en petit comité ou en tête-à-tête qu\'en grande assemblée : choisissez vos cadres plutôt que de forcer ceux qui ne vous ressemblent pas.',
            'question' => 'Dans quels cadres mon autorité est-elle naturelle et reçue ?', 'step' => 'Proposer de conduire un petit groupe plutôt qu\'une grande assemblée', 'plan' => 'CROISSANCE'],
        ['id' => 'warm-but-shy', 'kind' => 'paradox', 'a' => ['E1', 'high'], 'b' => ['N4', 'high'], 'weight' => 40,
            'title' => 'Chaleureux, mais vite intimidé',
            'text' => 'Vous aimez vraiment les gens, et pourtant vous vous sentez facilement gêné en public. Ce n\'est pas de la froideur : c\'est de la timidité sur un fond de chaleur. Les autres ne la voient pas toujours ; un mot simple de votre part suffit souvent à la dissiper.',
            'question' => 'Qu\'est-ce que je n\'ose pas dire alors que je le pense chaleureusement ?', 'step' => 'Dire un mot d\'appréciation sincère à une personne cette semaine', 'plan' => 'CROISSANCE'],
        ['id' => 'drive-and-worry', 'kind' => 'risk', 'a' => ['C4', 'high'], 'b' => ['N1', 'high'], 'weight' => 46,
            'title' => 'Une ambition portée par l\'inquiétude',
            'text' => 'Votre goût d\'accomplir est élevé, et l\'inquiétude vous accompagne. Les deux se nourrissent : l\'ambition fait avancer, l\'inquiétude fait douter d\'en faire assez. À long terme, ce couple épuise ; il demande des moments où quelque chose est « assez bien ».',
            'question' => 'Qu\'est-ce qui serait « assez » pour que je me repose ?', 'step' => 'Fixer un critère de « suffisant » pour un projet en cours', 'plan' => 'HYGIENE'],
        ['id' => 'bold-and-careful', 'kind' => 'calling', 'a' => ['O4', 'high'], 'b' => ['C6', 'high'], 'weight' => 36,
            'title' => 'Une audace qui réfléchit',
            'text' => 'Vous aimez la nouveauté et vous pesez vos décisions. Cette combinaison est rare : elle permet d\'innover sans tout casser. Les communautés ont besoin de personnes capables de proposer du nouveau et d\'en mesurer le coût.',
            'question' => 'Quelle idée nouvelle ai-je déjà bien examinée et pourrais-je proposer ?', 'step' => 'Présenter une idée nouvelle, avec ses risques, à une personne de confiance', 'plan' => 'DEVELOPPEMENT'],
        ['id' => 'heart-and-edge', 'kind' => 'tension', 'a' => ['A6', 'high'], 'b' => ['A4', 'low'], 'weight' => 38,
            'title' => 'Un cœur tendre, une voix qui s\'affirme',
            'text' => 'Vous ressentez beaucoup de compassion et vous n\'hésitez pas à contredire. Ces deux élans peuvent se heurter : on vous perçoit parfois plus dur que vous ne l\'êtes. Dire d\'abord ce que vous ressentez pour la personne rend votre franchise plus facile à recevoir.',
            'question' => 'Comment la personne perçoit-elle mon désaccord ?', 'step' => 'Commencer un désaccord par ce que vous appréciez chez la personne', 'plan' => 'CROISSANCE'],
        ['id' => 'ideas-and-order', 'kind' => 'tension', 'a' => ['O1', 'high'], 'b' => ['C2', 'low'], 'weight' => 38,
            'title' => 'Beaucoup d\'idées, des finitions fragiles',
            'text' => 'Votre imagination produit plus de pistes que votre sens de l\'ordre n\'en peut finir. Ce n\'est pas un défaut de caractère mais une répartition d\'énergie : l\'élan est de votre côté, la structure peut venir d\'un cadre ou d\'un partenaire.',
            'question' => 'Quelle idée mérite d\'être terminée avant d\'en ouvrir une autre ?', 'step' => 'Choisir une idée, la terminer, puis seulement en lancer une autre', 'plan' => 'INTENDANCE'],
        ['id' => 'anger-and-edge', 'kind' => 'risk', 'a' => ['N2', 'high'], 'b' => ['A4', 'low'], 'weight' => 42,
            'title' => 'Une irritation qui se voit',
            'text' => 'Vous vous irritez assez vite et vous cédez peu dans les désaccords. Cela peut vous coûter des relations auxquelles vous tenez. Repérer ce qui déclenche l\'irritation et décider à l\'avance d\'une pause avant de répondre protège ces relations.',
            'question' => 'Qu\'est-ce qui déclenche mon irritation, et quel est mon premier signe ?', 'step' => 'Convenir d\'un mot de pause avec une personne proche', 'plan' => 'HYGIENE'],
    ];

    /**
     * Lectures internes aux dons : deux dons du podium qui, réunis, dessinent un appel plus précis que chacun.
     * Les noms de rôles sont des illustrations, pas des fonctions officielles.
     */
    private const GIFT_PAIRS = [
        ['id' => 'teacher-shepherd', 'codes' => ['ENSEIGNEMENT', 'PASTORAT'], 'kind' => 'calling', 'weight' => 40, 'title' => 'Enseigner en prenant soin',
            'text' => 'Vous transmettez la vérité et vous veillez sur ceux qui la reçoivent : un enseignement qui accompagne ne laisse personne en route.',
            'question' => 'Qui pourrais-je enseigner et suivre sur la durée ?', 'step' => 'Proposer d\'accompagner un petit groupe d\'étude', 'plan' => 'TRANSMISSION'],
        ['id' => 'lucid-compassion', 'codes' => ['MISERICORDE', 'DISCERNEMENT'], 'kind' => 'calling', 'weight' => 40, 'title' => 'Une compassion lucide',
            'text' => 'Vous ressentez la peine des autres et vous voyez clair dans les situations. Cette lucidité évite la compassion qui se laisse manipuler et protège ceux que vous aidez.',
            'question' => 'Qui a besoin à la fois de mon écoute et de ma clarté ?', 'step' => 'Accompagner une personne en difficulté avec une écoute, puis un conseil précis', 'plan' => 'TRANSMISSION'],
        ['id' => 'truth-and-mercy', 'codes' => ['PROPHETIE', 'MISERICORDE'], 'kind' => 'tension', 'weight' => 44, 'title' => 'La vérité et la tendresse',
            'text' => 'Vous portez une parole franche et une compassion vive : deux élans qui tirent chacun de leur côté. Quand ils s\'accordent, vous dites des choses dures avec une douceur qui fait que l\'on peut les entendre. Quand ils s\'opposent, vous hésitez ou vous heurtez.',
            'question' => 'Qu\'est-ce que je me retiens de dire par peur de blesser ?', 'step' => 'Dire une vérité difficile à une personne, avec ce que vous appréciez chez elle', 'plan' => 'CROISSANCE'],
        ['id' => 'open-door', 'codes' => ['EVANGELISATION', 'HOSPITALITE'], 'kind' => 'calling', 'weight' => 40, 'title' => 'Une porte ouverte',
            'text' => 'Vous annoncez et vous accueillez : les personnes qui franchissent votre seuil se sentent attendues. Votre maison ou votre table peut devenir un lieu où la foi se raconte sans pression.',
            'question' => 'Qui pourrais-je inviter à ma table ce mois-ci ?', 'step' => 'Inviter une personne éloignée de l\'Église à un repas', 'plan' => 'TRANSMISSION'],
        ['id' => 'concrete-generosity', 'codes' => ['SERVICE', 'LIBERALITE'], 'kind' => 'calling', 'weight' => 36, 'title' => 'Une générosité concrète',
            'text' => 'Vous donnez votre temps et vos ressources : on peut compter sur vous quand il faut passer des paroles aux actes. Veillez à ne pas vous épuiser à répondre à tous les besoins.',
            'question' => 'À quels besoins suis-je appelé à répondre, et lesquels ne sont pas pour moi ?', 'step' => 'Choisir une cause à soutenir et en laisser une autre', 'plan' => 'INTENDANCE'],
        ['id' => 'faith-prayer', 'codes' => ['FOI', 'INTERCESSION'], 'kind' => 'calling', 'weight' => 40, 'title' => 'Une foi qui prie',
            'text' => 'Vous croyez que Dieu agit et vous le lui demandez : la foi et l\'intercession forment ensemble un appel à soutenir d\'autres personnes et des projets dans la durée de la prière.',
            'question' => 'Qui ou quoi Dieu me demande-t-il de porter dans la prière ?', 'step' => 'Tenir un carnet de prière de trois personnes ou projets', 'plan' => 'VIE'],
        ['id' => 'wisdom-leads', 'codes' => ['PRESIDENCE', 'SAGESSE'], 'kind' => 'calling', 'weight' => 38, 'title' => 'Conduire avec sagesse',
            'text' => 'Vous savez organiser et décider, et vous le faites avec discernement : on vous confie des responsabilités parce que vous pesez avant d\'agir.',
            'question' => 'Quelle décision collective pourrais-je éclairer ?', 'step' => 'Offrir un avis réfléchi sur une décision à prendre dans votre communauté', 'plan' => 'TRANSMISSION'],
    ];

    /** Nombre de lectures gardées. */
    public const MAX = 6;

    /**
     * @param array<string, ?array> $r           rapports par test
     * @param array<string, mixed>  $convergence résultat de ProfileSynthesis::convergence (axes, divergent)
     * @param ?array<string, mixed> $life        équilibre de vie (avg, priorities, supports)
     *
     * @return list<array<string, mixed>>
     */
    public function analyze(array $r, array $convergence = [], ?array $life = null, int $limit = self::MAX): array
    {
        $found = array_filter([
            $this->adaptationStrain($r, $life),
            $this->motivationVersusBehaviour($r),
            $this->visibleGiftReservedTemperament($r),
            $this->hiddenGiftOpenTemperament($r),
            $this->sensitivityAndCare($r),
            $this->directnessAndCare($r),
            $this->reliabilityAndConcreteGift($r),
            $this->curiosityAndThoughtGift($r),
            $this->pressureSignature($r),
            $this->growthPath($r),
            $this->coherence($convergence),
            $this->divergence($convergence),
            $this->lifeGround($r, $life),
        ]);
        $found = array_merge(array_values($found), $this->internalBigFive($r), $this->internalGifts($r));
        foreach ($found as &$f) {
            $count = \count($f['sources']);
            $f['weight'] += 6 * ($count - 1);
            $f['internal'] = 1 === $count;
            $f['confidence'] = match (true) {
                $count >= 3 => 'Recoupé par '.$count.' tests',
                2 === $count => 'Recoupé par 2 tests',
                default => 'Lu dans ce seul test : à recouper',
            };
            $f['kindLabel'] = self::KINDS[$f['kind']]['label'];
        }
        unset($f);
        usort($found, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return \array_slice($found, 0, $limit);
    }

    // ───────────────────────────── Règles ─────────────────────────────

    /** Un profil adapté très éloigné du profil naturel : l'effort se paie, surtout avec une sensibilité émotionnelle élevée. */
    private function adaptationStrain(array $r, ?array $life): ?array
    {
        $disc = $r['disc']['disc'] ?? null;
        if (!$disc || $disc['gap'] < 25) {
            return null;
        }
        $evidence = [['DISC', 'Écart de '.$disc['gap'].' points entre votre profil naturel ('.$disc['profile']['name'].') et votre profil adapté'.($disc['adaptedPrimary'] === $disc['primary'] ? ' (même dimension dominante, mais répartie autrement).' : ' ('.$disc['adaptedProfile']['name'].').')]];
        $sources = ['disc'];
        $weight = 62 + min(14, (int) (($disc['gap'] - 25) / 3));
        $text = 'Votre contexte vous demande de vivre assez loin de votre nature'.($disc['more'] ? ' : '.lcfirst(implode(', ', $disc['more'])) : '').'. '
            .'Cela peut durer longtemps sans se voir, mais cela consomme de l\'énergie.';
        $n = $r['bigfive']['bf']['domains']['N'] ?? null;
        if ($n && 'high' === $n['level']) {
            $evidence[] = ['Big Five', 'Sensibilité émotionnelle élevée : vous ressentez fortement la pression.'];
            $sources[] = 'bigfive';
            $weight += 12;
            $text .= ' Avec une sensibilité émotionnelle élevée, la fatigue peut s\'installer avant que vous ne la remarquiez.';
        }
        if ($e = $r['enneagramme']['enneagram'] ?? null) {
            $evidence[] = ['Ennéagramme', 'Votre peur profonde : '.lcfirst($e['profile']['fear'])];
            $sources[] = 'enneagramme';
        }
        if ($life && $life['priorities']) {
            $evidence[] = ['Équilibre de vie', 'Priorités signalées : '.lcfirst(implode(', ', $life['priorities'])).'.'];
            $sources[] = 'equilibre';
        }

        return $this->insight('strain', 'risk', 'Vous vous adaptez beaucoup à votre contexte', $text, $evidence, $sources, $weight,
            'Où puis-je être moi-même sans avoir à m\'expliquer, et quand ?', 'Poser un temps de ressourcement fixe chaque semaine', 'HYGIENE');
    }

    /** Ce qu'on voit (DISC) et ce qui pousse au fond (ennéagramme) : accord ou paradoxe. */
    private function motivationVersusBehaviour(array $r): ?array
    {
        $disc = $r['disc']['disc'] ?? null;
        $enn = $r['enneagramme']['enneagram'] ?? null;
        if (!$disc || !$enn || !isset(self::ENNEAGRAM_EXPECTS[$enn['type']])) {
            return null;
        }
        $type = (int) $enn['type'];
        $expected = self::ENNEAGRAM_EXPECTS[$type];
        $evidence = [
            ['DISC', 'Votre manière d\'agir : '.$disc['profile']['name'].'.'],
            ['Ennéagramme', 'Type '.$type.' · '.$enn['profile']['name'].' : '.lcfirst(rtrim($enn['profile']['motivation'], '.')).'.'],
        ];
        if (\in_array($disc['primary'], $expected, true)) {
            return $this->insight('consistent', 'convergence', 'Ce que vous faites et ce qui vous pousse s\'accordent',
                'Votre manière d\'agir ('.lcfirst($disc['profile']['name']).') est celle qu\'on voit d\'ordinaire chez un type '.$type.'. Vos actes expriment ce qui vous motive vraiment : c\'est une force d\'unité, et on sent peu de décalage entre vous et ce que vous montrez.',
                $evidence, ['disc', 'enneagramme'], 48, 'Qu\'est-ce que cette unité me permet d\'oser que d\'autres n\'osent pas ?', 'Écrire ce que vous voulez vraiment servir', 'VIE');
        }
        $names = array_map(fn ($l) => lcfirst(\App\Personnalite\Psychometrie\Content::get('disc.dimensions')[$l]['name']), $expected);

        return $this->insight('paradox', 'paradox', 'Ce qui se voit n\'est pas tout ce qui vous porte',
            'Vous agissez en profil '.lcfirst($disc['profile']['name']).', alors que ce qui vous motive ('.lcfirst(rtrim($enn['profile']['motivation'], '.')).') s\'exprime plus souvent par un profil '.implode(' ou ', $names).'. '
            .'Cela arrive : on apprend parfois à agir autrement que ce qu\'on ressent, par éducation, par contexte ou par foi. À explorer sans en faire un verdict : c\'est une hypothèse, pas une étiquette.',
            $evidence, ['disc', 'enneagramme'], 58, 'Dans quelles situations est-ce que j\'agis à l\'inverse de ce que je ressens ?', 'Choisir une situation et agir de façon plus fidèle à ce que vous ressentez', 'CROISSANCE');
    }

    /** Un don qui s'exerce devant les autres, dans un tempérament réservé. */
    private function visibleGiftReservedTemperament(array $r): ?array
    {
        $e = $r['bigfive']['bf']['domains']['E'] ?? null;
        $gift = $this->topGift($r, self::PUBLIC_GIFTS);
        if (!$e || !$gift || 'low' !== $e['level']) {
            return null;
        }

        return $this->insight('public-gift-quiet', 'tension', 'Un don qui se voit, un tempérament qui se ménage',
            'Votre don de '.mb_strtolower($gift['name']).' vous place devant les autres, alors que votre tempérament est plutôt réservé. Ce n\'est pas un obstacle : beaucoup de personnes appelées à parler ou à conduire sont ainsi. '
            .'Cela demande de préparer, de choisir des cadres à taille humaine et de prévoir du repos après les moments d\'exposition.',
            [['Dons spirituels', 'Don mis en avant : '.$gift['name'].'.'], ['Big Five', 'Extraversion faible : énergie plutôt puisée dans le calme.']],
            ['dons', 'bigfive'], 60, 'Quel cadre me permet d\'exercer ce don sans m\'épuiser ?', 'Proposer d\'exercer ce don dans un petit groupe d\'abord', 'CROISSANCE');
    }

    /** Un don discret, un tempérament tourné vers les autres : chercher le lien. */
    private function hiddenGiftOpenTemperament(array $r): ?array
    {
        $e = $r['bigfive']['bf']['domains']['E'] ?? null;
        $gifts = array_column($r['dons']['gifts']['top'] ?? [], 'code');
        if (!$e || !$gifts || 'high' !== $e['level'] || array_diff($gifts, self::HIDDEN_GIFTS)) {
            return null;
        }

        return $this->insight('hidden-gift-open', 'calling', 'Un don discret, un tempérament qui rassemble',
            'Vos dons les plus marqués s\'exercent plutôt dans l\'ombre (service, prière, compassion), tandis que votre énergie vous porte vers les autres. Associez les deux : votre aisance relationnelle peut ouvrir des portes à un service que d\'autres n\'oseraient pas proposer.',
            [['Dons spirituels', 'Dons principaux : '.implode(', ', array_column($r['dons']['gifts']['top'], 'name')).'.'], ['Big Five', 'Extraversion élevée.']],
            ['dons', 'bigfive'], 46, 'À qui puis-je offrir ce service pour la première fois ?', 'Proposer ce service à une personne précise', 'TRANSMISSION');
    }

    /** Sensibilité émotionnelle forte et don de soin : fatigue de compassion. */
    private function sensitivityAndCare(array $r): ?array
    {
        $n = $r['bigfive']['bf']['domains']['N'] ?? null;
        $gift = $this->topGift($r, self::CARE_GIFTS);
        if (!$n || !$gift || 'high' !== $n['level']) {
            return null;
        }

        return $this->insight('care-sensitive', 'risk', 'Votre sensibilité nourrit votre don, et peut l\'épuiser',
            'Votre don de '.mb_strtolower($gift['name']).' s\'appuie sur une grande sensibilité : vous percevez ce que les autres vivent. C\'est ce qui rend votre présence précieuse, et ce qui expose à la fatigue de compassion. '
            .'Fixer à l\'avance où s\'arrête ce que vous portez protège ce don.',
            [['Dons spirituels', 'Don de soin : '.$gift['name'].'.'], ['Big Five', 'Sensibilité émotionnelle élevée.']],
            ['dons', 'bigfive'], 64, 'Qu\'est-ce que je porte qui ne m\'appartient pas ?', 'Définir une limite claire (horaire, nombre de personnes suivies)', 'HYGIENE');
    }

    /** Un tempérament direct et un don de soin : apprendre la douceur sans perdre la franchise. */
    private function directnessAndCare(array $r): ?array
    {
        $a = $r['bigfive']['bf']['domains']['A'] ?? null;
        $gift = $this->topGift($r, self::CARE_GIFTS);
        if (!$a || !$gift || 'low' !== $a['level']) {
            return null;
        }

        return $this->insight('care-direct', 'tension', 'Un don de soin, un tempérament direct',
            'Votre don de '.mb_strtolower($gift['name']).' appelle de la douceur ; votre tempérament va droit au but. Ces deux traits ne s\'opposent pas : votre franchise rassure ceux qui veulent la vérité. Elle gagne à être précédée d\'une écoute visible.',
            [['Dons spirituels', 'Don de soin : '.$gift['name'].'.'], ['Big Five', 'Agréabilité plutôt faible : franchise, esprit critique.']],
            ['dons', 'bigfive'], 52, 'Qu\'est-ce que la personne a besoin d\'entendre avant ma remarque ?', 'Reformuler ce que l\'autre a dit avant de répondre, trois fois cette semaine', 'CROISSANCE');
    }

    /** Conscience élevée et don concret : une fiabilité qui donne corps au don. */
    private function reliabilityAndConcreteGift(array $r): ?array
    {
        $c = $r['bigfive']['bf']['domains']['C'] ?? null;
        $gift = $this->topGift($r, self::CONCRETE_GIFTS);
        if (!$c || !$gift || 'high' !== $c['level']) {
            return null;
        }

        return $this->insight('reliable-gift', 'calling', 'Une fiabilité qui donne corps à votre don',
            'Votre sens de l\'engagement et de l\'organisation donne un corps à votre don de '.mb_strtolower($gift['name']).' : on peut compter sur vous dans la durée. Les communautés manquent souvent de personnes qui tiennent leurs engagements ; vous en êtes une.',
            [['Dons spirituels', 'Don : '.$gift['name'].'.'], ['Big Five', 'Conscience élevée : rigueur, constance.']],
            ['dons', 'bigfive'], 44, 'Quel engagement durable suis-je prêt à prendre ?', 'Choisir un service régulier et l\'inscrire à votre agenda', 'INTENDANCE');
    }

    /** Ouverture élevée et don de pensée : le chercheur qui transmet. */
    private function curiosityAndThoughtGift(array $r): ?array
    {
        $o = $r['bigfive']['bf']['domains']['O'] ?? null;
        $gift = $this->topGift($r, self::THOUGHT_GIFTS);
        if (!$o || !$gift || 'high' !== $o['level']) {
            return null;
        }

        return $this->insight('thought-gift', 'calling', 'Un esprit curieux au service de la parole',
            'Votre curiosité et votre goût des idées alimentent votre don de '.mb_strtolower($gift['name']).' : vous cherchez, puis vous transmettez. Veillez à transformer ce que vous découvrez en quelque chose que les autres peuvent saisir et vivre.',
            [['Dons spirituels', 'Don : '.$gift['name'].'.'], ['Big Five', 'Ouverture élevée : curiosité intellectuelle, imagination.']],
            ['dons', 'bigfive'], 44, 'Qu\'ai-je découvert récemment que quelqu\'un a besoin d\'entendre ?', 'Rédiger en dix lignes ce que vous avez appris et le partager', 'DEVELOPPEMENT');
    }

    /** Deux regards sur la même épreuve : ce qui se passe quand la pression monte. */
    private function pressureSignature(array $r): ?array
    {
        $disc = $r['disc']['disc'] ?? null;
        $enn = $r['enneagramme']['enneagram'] ?? null;
        if (!$disc || !$enn) {
            return null;
        }
        $stress = $enn['stress'];
        $text = 'Deux outils décrivent le même moment. Pour le DISC : '.lcfirst(rtrim($disc['combo']['signs'], '.')).'. Pour l\'ennéagramme, la pression vous entraîne vers le type '.$stress['n'].' ('.lcfirst($stress['name']).') ; votre signal d\'alerte : '.lcfirst(rtrim($enn['x']['signs'], '.')).'. '
            .'Repérer ces signes tôt vous permet de vous arrêter avant que la tension ne s\'installe.';

        return $this->insight('pressure', 'risk', 'Votre signature sous pression', $text,
            [['DISC', 'Signes : '.lcfirst(rtrim($disc['combo']['signs'], '.')).'.'], ['Ennéagramme', 'Sous pression, vers le type '.$stress['n'].' ('.$stress['name'].') ; signal d\'alerte : '.lcfirst(rtrim($enn['x']['signs'], '.')).'.']],
            ['disc', 'enneagramme'], 56, 'Quels sont les trois premiers signes qui me disent que je glisse ?', 'Écrire vos trois signes d\'alerte et les confier à un partenaire de redevabilité', 'HYGIENE');
    }

    /** Où grandir, d'après l'ennéagramme et le DISC. */
    private function growthPath(array $r): ?array
    {
        $disc = $r['disc']['disc'] ?? null;
        $enn = $r['enneagramme']['enneagram'] ?? null;
        if (!$disc || !$enn) {
            return null;
        }

        return $this->insight('growth', 'growth', 'Où grandir en priorité',
            'Quand vous allez bien, l\'ennéagramme vous voit rejoindre le type '.$enn['grow']['n'].' ('.lcfirst($enn['grow']['name']).') : '.lcfirst(rtrim($enn['x']['growthText'], '.')).'. Côté DISC, un geste concret : '.lcfirst(rtrim($disc['combo']['grow'][0], '.')).'. Un seul chantier à la fois suffit.',
            [['Ennéagramme', 'Chemin de croissance : type '.$enn['grow']['n'].'.'], ['DISC', 'Pour grandir : '.lcfirst(rtrim($disc['combo']['grow'][0], '.')).'.']],
            ['disc', 'enneagramme'], 50, 'Quel est le plus petit pas qui irait dans ce sens ?', 'Choisir un chantier de caractère dans votre Plan de Croissance', 'CROISSANCE');
    }

    /** Trois axes (relation, rythme, structure) qui se rejoignent. */
    private function coherence(array $convergence): ?array
    {
        $axes = $convergence['axes'] ?? [];
        $agree = array_filter($axes, fn ($a) => true === $a['agree']);
        if (\count($agree) < 2 || ($convergence['divergent'] ?? [])) {
            return null;
        }
        $labels = array_map(fn ($a) => lcfirst($a['mean'] > 0.25 ? $a['right'] : ($a['mean'] < -0.25 ? $a['left'] : 'ni trop '.lcfirst($a['left']).' ni trop '.lcfirst($a['right']))), $agree);

        return $this->insight('coherent', 'convergence', 'Un profil cohérent d\'un test à l\'autre',
            'Vos tests disent la même chose sur '.\count($agree).' axes : '.implode(', ', $labels).'. Quand des méthodes différentes se rejoignent, le trait est solide : vous pouvez vous appuyer dessus pour choisir où vous engager.',
            array_map(fn ($a) => [$a['left'].' / '.$a['right'], 'Tests concordants.'], array_values($agree)), ['disc', 'bigfive', 'type'], 42,
            'Qu\'est-ce que cette solidité m\'autorise à décider maintenant ?', 'Noter une décision que vous repoussez et la prendre', 'VIE');
    }

    /** Un axe sur lequel les tests divergent : une zone à observer. */
    private function divergence(array $convergence): ?array
    {
        $div = $convergence['divergent'] ?? [];
        if (!$div) {
            return null;
        }
        $names = array_map(fn ($k) => $convergence['axes'][$k]['left'].' / '.$convergence['axes'][$k]['right'], $div);

        return $this->insight('divergent', 'tension', 'Une zone à observer : vos tests ne disent pas tout à fait la même chose',
            'Sur '.(\count($names) > 1 ? 'les axes' : 'l\'axe').' « '.implode(' », « ', $names).' », vos tests divergent. Ce n\'est pas une erreur : une méthode mesure ce que vous dites, une autre vos traits, une autre vos préférences. '
            .'C\'est souvent là que vous vous adaptez, ou que vous vous voyez autrement que les autres ne vous voient. Demander à trois proches leur regard éclaire ce point.',
            array_map(fn ($n) => [$n, 'Les tests divergent.'], $names), ['disc', 'bigfive', 'type'], 55,
            'Et eux, comment me voient-ils sur ce point ?', 'Demander le regard de trois proches sur ce point', 'VIE');
    }

    /** Votre réalité de vie : un terrain où les dons s'exercent. */
    private function lifeGround(array $r, ?array $life): ?array
    {
        if (!$life || !$life['priorities'] || !($r['dons']['gifts']['top'] ?? null)) {
            return null;
        }

        return $this->insight('ground', 'growth', 'Vos dons s\'exercent dans la vie que vous avez',
            'Votre équilibre de vie signale des priorités ('.lcfirst(implode(', ', $life['priorities'])).'). Vos dons ne sont pas à exercer à côté de cette réalité mais à l\'intérieur : commencez par là où vous êtes, avant d\'ajouter un nouveau service.',
            [['Équilibre de vie', 'Priorités : '.lcfirst(implode(', ', $life['priorities'])).'.'], ['Dons spirituels', 'Dons principaux : '.implode(', ', array_column($r['dons']['gifts']['top'], 'name')).'.']],
            ['equilibre', 'dons'], 40, 'Où mes dons sont-ils déjà attendus, tout près de moi ?', 'Nommer une personne de votre entourage proche que votre don peut servir', 'INTENDANCE');
    }

    /** @return list<array<string, mixed>> */
    private function internalBigFive(array $r): array
    {
        $domains = $r['bigfive']['bf']['domains'] ?? null;
        if (!$domains) {
            return [];
        }
        $facet = function (string $code) use ($domains): ?array {
            foreach ($domains as $d) {
                if (isset($d['facets'][$code])) {
                    return $d['facets'][$code];
                }
            }

            return null;
        };
        $out = [];
        foreach (self::BIGFIVE_PAIRS as $rule) {
            [$ca, $la] = $rule['a'];
            [$cb, $lb] = $rule['b'];
            $a = $facet($ca);
            $b = $facet($cb);
            if (!$a || !$b || $la !== $a['level'] || $lb !== $b['level']) {
                continue;
            }
            $say = fn (array $f, string $level) => $f['label'].' : niveau '.('high' === $level ? 'élevé' : 'faible').'.';
            $out[] = $this->insight($rule['id'], $rule['kind'], $rule['title'], $rule['text'], [['Big Five', $say($a, $la)], ['Big Five', $say($b, $lb)]], ['bigfive'], $rule['weight'],
                $rule['question'], $rule['step'], $rule['plan']);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function internalGifts(array $r): array
    {
        $top = $r['dons']['gifts']['top'] ?? [];
        $names = array_column($top, 'name', 'code');
        $out = [];
        foreach (self::GIFT_PAIRS as $rule) {
            [$a, $b] = $rule['codes'];
            if (!isset($names[$a], $names[$b])) {
                continue;
            }
            $out[] = $this->insight($rule['id'], $rule['kind'], $rule['title'], $rule['text'], [['Dons spirituels', $names[$a].' et '.$names[$b].' figurent parmi vos dons principaux.']], ['dons'], $rule['weight'],
                $rule['question'], $rule['step'], $rule['plan']);
        }

        return $out;
    }

    // ───────────────────────────── Outils ─────────────────────────────

    /** Premier don du podium appartenant à la liste donnée, sinon null. */
    private function topGift(array $r, array $codes): ?array
    {
        foreach ($r['dons']['gifts']['top'] ?? [] as $g) {
            if (\in_array($g['code'], $codes, true)) {
                return $g;
            }
        }

        return null;
    }

    /** @param list<array{0: string, 1: string}> $evidence */
    private function insight(string $id, string $kind, string $title, string $text, array $evidence, array $sources, int $weight, string $question, string $step, string $plan): array
    {
        return ['id' => $id, 'kind' => $kind, 'title' => $title, 'text' => $text, 'evidence' => $evidence, 'sources' => $sources, 'weight' => $weight,
            'question' => $question, 'step' => ['label' => $step, 'plan' => $plan]];
    }
}
