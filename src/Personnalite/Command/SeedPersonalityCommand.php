<?php

namespace App\Personnalite\Command;

use App\Personnalite\Entity\PersonalityAnswer;
use App\Personnalite\Entity\PersonalityProfile;
use App\Personnalite\Entity\PersonalityQuestion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:personality', description: 'Seed personality questions and profiles')]
class SeedPersonalityCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Clear existing
        $io->section('Clearing existing personality questions & profiles...');
        $this->em->createQuery('DELETE FROM App\Personnalite\Entity\PersonalityAnswer')->execute();
        $this->em->createQuery('DELETE FROM App\Personnalite\Entity\PersonalityQuestion')->execute();
        $this->em->createQuery('DELETE FROM App\Personnalite\Entity\PersonalityProfile')->execute();

        $this->seedEquilibre($io);
        $this->seedHygiene($io);

        $this->em->flush();
        $io->success('Personality questions and profiles seeded successfully!');

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EQUILIBRE DE VIE (Roue de la vie)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedEquilibre(SymfonyStyle $io): void
    {
        $io->section('Équilibre de Vie Questions...');
        $domains = [
            ['sante', 'Santé & Vitalité', 'Sur une échelle de 1 à 10, comment évaluez-vous votre santé physique et votre niveau de vitalité au quotidien ?'],
            ['famille', 'Relations Familiales', 'Sur une échelle de 1 à 10, quel est votre niveau d’épanouissement dans vos relations familiales ?'],
            ['finances', 'Finances & Sécurité', 'Sur une échelle de 1 à 10, comment évaluez-vous votre niveau de sérénité et de sécurité financière ?'],
            ['carriere', 'Carrière & Projets', 'Sur une échelle de 1 à 10, quel est votre niveau d’accomplissement dans votre carrière et vos projets professionnels ?'],
            ['spiritualite', 'Spiritualité & Alignement', 'Sur une échelle de 1 à 10, comment évaluez-vous votre relation avec Dieu et votre vie spirituelle ?'],
            ['social', 'Vie Sociale & Amitié', 'Sur une échelle de 1 à 10, quel est votre niveau de satisfaction dans vos relations amicales et sociales ?'],
            ['personnel', 'Croissance Personnelle', 'Sur une échelle de 1 à 10, comment évaluez-vous vos progrès dans votre développement personnel cette année ?'],
            ['loisirs', 'Loisirs & Plaisir', 'Sur une échelle de 1 à 10, combien de temps de qualité consacrez-vous à vos loisirs et passions ?'],
        ];

        $scaleAnswers = [];
        for ($n = 1; $n <= 10; $n++) {
            $scaleAnswers[] = ['text' => (string)$n, 'value' => (string)$n];
        }

        foreach ($domains as $i => [$cat, $title, $text]) {
            $q = (new PersonalityQuestion())->setTestType('EQUILIBRE')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($scaleAnswers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        $profiles = [
            ['SANTE', 'Santé & Vitalité', 'Votre corps et votre niveau d\'énergie physique représentent votre pilier dominant d\'équilibre en ce moment.', 'Vigueur, conscience corporelle fine, hygiène de vie proactive', 'Risque d\'hypocondrie ou d\'obsession de la perfection physique', 'Le corps comme temple et premier véhicule de l\'énergie de vie.', 'Attentif aux besoins vitaux des autres, protecteur', 'Métiers du bien-être, de l\'accompagnement sportif ou de la nutrition', 'Gère son équilibre psychologique par le soin physique', 'Intégrer le lâcher-prise et accepter les limites naturelles de son enveloppe charnelle.'],
            ['FAMILLE', 'Famille & Intimité', 'Le foyer et la qualité des relations familiales proches sont le socle de votre équilibre.', 'Soutien affectif solide, sens des racines, écoute des siens', 'Risque de sur-investissement protecteur ou de dépendances émotives', 'Créer un cocon sécurisant d\'amour et de transmission.', 'Protecteur, aimant, présent pour son cercle restreint', 'Accompagnement familial, travail social, médiation ou conseil d\'orientation', 'Sensible aux tensions familiales, a besoin d\'harmonie chez soi', 'Poser des limites saines et encourager l\'autonomie de chaque membre du foyer.'],
            ['FINANCES', 'Finances & Abondance', 'La sécurité et la liberté financières constituent votre principal centre d\'intérêt d\'équilibre actuel.', 'Pragmatisme, sens de l\'économie, gestion de budget rigoureuse', 'Peur irrationnelle du manque ou matérialisme excessif', 'L\'argent comme moyen d\'action et de sécurité matérielle.', 'Gestionnaire responsable, transparent sur les questions d\'argent', 'Conseil en investissement, gestion de patrimoine, entrepreneuriat', 'Canalise l\'anxiété par le contrôle des chiffres et de la rentabilité', 'Apprendre à apprécier l\'abondance non matérielle et faire preuve de génériosité spontanée.'],
            ['CARRIERE', 'Carrière & Accomplissement', 'Votre réalisation professionnelle et l\'atteinte de vos objectifs de carrière guident votre équilibre.', 'Ambition, détermination, focus sur les résultats, leadership inspirant', 'Risque de surmenage, confusion entre identité et statut professionnel', 'Créer de la valeur et s\'accomplir par le travail constructif.', 'Exigeant, orienté performance, apprécie la reconnaissance de ses pairs', 'Management de projets, direction d\'équipe, entrepreneuriat à fort impact', 'Canalise le stress par l\'action méthodique et le travail intensif', 'Distinguer sa valeur humaine intrinsèque de ses réussites ou échecs professionnels.'],
            ['SPIRITUALITE', 'Spiritualité & Alignement', 'Votre relation avec Dieu et votre alignement intérieur sont vos guides dominants.', 'Sérénité, intégrité profonde, résilience face aux crises, sagesse', 'Risque de fuite de la réalité matérielle ou de dogmatisme', 'Vivre chaque instant en harmonie avec sa vérité supérieure.', 'Pacificateur, cherche des partages d\'âme authentiques', 'Coaching de vie, philosophie, écriture, animation de cercles éthiques', 'Trouve son calme dans le silence, la nature et la contemplation', 'Ancrer ses convictions spirituelles dans des actions concrètes et quotidiennes.'],
            ['SOCIAL', 'Vie Sociale & Amitié', 'Le partage social, l\'amitié et l\'appartenance à une communauté portent votre équilibre.', 'Loyauté, convivialité, sens du réseau, joie du partage collectif', 'Risque de superficialité ou de dépendance au regard social', 'Tisser des liens chaleureux pour célébrer la vie ensemble.', 'Extraverti, facilitateur, pilier de son cercle social', 'Événementiel, relations publiques, animation de communautés', 'S\'énergise au contact des autres et par les échanges animés', 'Cultiver des relations plus intimes et savoir apprécier la solitude.'],
            ['PERSONNEL', 'Croissance Personnelle', 'L\'apprentissage continu, l\'introspection et le développement personnel sont vos priorités.', 'Curiosité intellectuelle, autonomie, résilience, désir d\'évolution', 'Risque d\'intellectualisation excessive ou d\'éternelle insatisfaction', 'Devenir la meilleure version de soi-même au quotidien.', 'Indépendant, cherche des relations qui favorisent la croissance mutuelle', 'Enseignement, conseil, coaching, psychothérapie, recherche', 'Gère son stress en cherchant le sens caché derrière les difficultés', 'Passer de l\'accumulation de connaissances théoriques à la mise en pratique réelle.'],
            ['LOISIRS', 'Loisirs & Plaisir', 'Le jeu, la créativité libre et les loisirs non productifs sont au centre de votre équilibre.', 'Joie de vivre, créativité spontanée, capacité à déconnecter du travail', 'Tendance à l\'évitement des responsabilités ou à la procrastination', 'Savourer la légèreté de l\'instant présent à travers la détente.', 'Ludique, amical, initie les moments de détente', 'Métiers de la création, des jeux, du divertissement sain ou du tourisme', 'Évacue ses tensions par le plaisir de créer sans obligation', 'Apprendre à structurer son temps de loisir pour qu\'il reste pleinement ressourçant.']
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('EQUILIBRE')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function seedHygiene(SymfonyStyle $io): void
    {
        $io->section('Hygiène de Vie Questions...');
        $axes = [
            // 1️⃣ Physique – Sommeil & Nutrition
            ['physique_sommeil_repos', 'Physique - Sommeil', 'Je me réveille chaque matin avec un sentiment de repos complet.'],
            ['physique_sommeil_energie', 'Physique - Vitalité', 'J’ai suffisamment d’énergie pour accomplir mes tâches quotidiennes sans fatigue excessive.'],
            ['physique_nutrition_savourer', 'Physique - Nutrition', 'Je prends le temps de savourer chaque repas, sans me sentir pressé·e.'],
            ['physique_hydratation_quantite', 'Physique - Hydratation', 'Je bois une quantité d’eau adéquate tout au long de la journée.'],
            ['physique_nutrition_concentration', 'Physique - Digestion', 'Je remarque que ma concentration reste stable après chaque repas.'],
            // 2️⃣ Physique – Activité physique & Hydratation
            ['physique_activite_mouvement', 'Physique - Mouvement', 'Je bouge (marche, étirements, sport) plusieurs fois pendant la journée.'],
            ['physique_activite_souplesse', 'Physique - Souplesse', 'Je ressens une souplesse corporelle satisfaisante (absence de raideurs).'],
            ['physique_activite_revitalise', 'Physique - Récupération', 'Après une activité physique, je me sens revitalisé·e plutôt que épuisé·e.'],
            ['physique_hydratation_travail', 'Physique - Hydratation au travail', 'Je consomme régulièrement de l’eau pendant mes pauses de travail.'],
            // 3️⃣ Mental – Gestion du stress & Repos cognitif
            ['mental_pause_cognitive', 'Mental - Repos cognitif', 'Je m’accorde des pauses mentales pour « déconnecter » et recharger mon attention.'],
            ['mental_stress_relachement', 'Mental - Gestion du stress', 'Lorsque le stress apparaît, je parviens à le relâcher rapidement.'],
            ['mental_loisir_calme', 'Mental - Calme', 'Je dédie du temps chaque semaine à une activité qui me procure du plaisir et du calme.'],
            ['mental_sommeil_alerte', 'Mental - Clarté', 'Je dors suffisamment pour me sentir mentalement alerte au réveil.'],
            // 4️⃣ Relationnel – Qualité des liens interpersonnels
            ['relationnel_ecoute_proches', 'Relationnel - Écoute', 'Je ressens une écoute véritable de la part de mes proches et collègues.'],
            ['relationnel_partage_moments', 'Relationnel - Partage', 'Je partage régulièrement des moments significatifs avec ma famille ou mon cercle d’amis.'],
            ['relationnel_expression_besoins', 'Relationnel - Expression', 'Je suis capable d’exprimer mes besoins et mes sentiments de façon claire.'],
            ['relationnel_soutien_reseau', 'Relationnel - Soutien', 'Je bénéficie d’un réseau de soutien fiable en cas de besoin.'],
            // 5️⃣ Spirituel – Sens & Alignement de vie
            ['spirituel_sens_profond', 'Spirituel - Sens', 'Je perçois un sens profond dans les activités que j’accomplis quotidiennement.'],
            ['spirituel_valeurs_alignement', 'Spirituel - Alignement', 'Je me sens aligné·e avec mes valeurs personnelles dans mes décisions quotidiennes.'],
            ['spirituel_paix_tranquillite', 'Spirituel - Paix intérieure', 'Je trouve une tranquillité intérieure même lorsque les circonstances sont difficiles.'],
            ['spirituel_pratique_esprit', 'Spirituel - Pratique personnelle', 'Je consacre du temps à une pratique (lecture, méditation, créativité…) qui nourrit mon esprit.'],
            // 6️⃣ Ressources – Gestion du temps & de l’énergie personnelle
            ['ressources_temps_priorites', 'Ressources - Gestion du temps', 'Je planifie ma journée de façon à respecter mes priorités essentielles.'],
            ['ressources_temps_controle', 'Ressources - Maîtrise du temps', 'Je sens que je contrôle mon emploi du temps plutôt que d’être submergé·e.'],
            ['ressources_projets_passion', 'Ressources - Projets personnels', 'Je consacre du temps chaque semaine à des projets qui me passionnent réellement.'],
            ['ressources_energie_non', 'Ressources - Limites', 'Je sais dire « non » quand une demande risque de surcharger mon énergie.'],
            ['ressources_recuperation_missions', 'Ressources - Récupération', 'Je me donne la possibilité de récupérer physiquement et mentalement entre deux missions.'],
            // 7️⃣ Environnement – Qualité de l’espace physique
            ['environnement_espace_ordre', 'Environnement - Clarté physique', 'Mon lieu de travail ou mon domicile est rangé et facilite ma concentration.'],
            ['environnement_lumiere_naturelle', 'Environnement - Lumière', 'J’ai accès à une lumière naturelle suffisante dans les espaces où je passe le plus de temps.'],
            ['environnement_niveau_sonore', 'Environnement - Niveau sonore', 'Le niveau sonore ambiant ne perturbe pas mon efficacité.'],
            ['environnement_nature_apaisement', 'Environnement - Nature', 'Je profite régulièrement d’espaces verts ou d’un environnement naturel apaisant.'],
        ];

        $scaleAnswers = [];
        for ($n = 1; $n <= 10; $n++) {
            $scaleAnswers[] = ['text' => (string)$n, 'value' => (string)$n];
        }

        foreach ($axes as $i => [$cat, $title, $text]) {
            $q = (new PersonalityQuestion())->setTestType('HYGIENE')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($scaleAnswers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        $profiles = [
            ['PHYSIQUE', 'Hygiène Physique Dominante', 'Votre score souligne que le pilier corporel (sommeil, nutrition, sport, repos) est votre fondation vitale prioritaire.', 'Excellente vitalité, régularité, respect des rythmes biologiques de base', 'Risque d\'orthorexie ou de discipline physique excessive et angoissée', 'Le corps comme premier réceptacle de l\'énergie de vie.', 'Soucieux du confort matériel et vital de son entourage', 'Métiers de la forme, de la santé holistique, de l\'accompagnement corporel', 'Régule son stress par le sommeil et l\'exercice de pleine nature', 'Savoir relâcher le contrôle corporel et s\'autoriser des écarts festifs ou relaxants.'],
            ['MENTAL', 'Hygiène Mentale Dominante', 'La régulation des pensées, le calme intérieur et la concentration sont vos axes majeurs d\'hygiène.', 'Clarté de focus, calme intérieur face au bruit ambiant, prise de recul intellectuelle', 'Tendance à ruminer dans la solitude ou à intellectualiser les blocages', 'La paix de l\'esprit comme boussole de sérénité.', 'Précis, posé, favorise les dialogues structurés et sans cris', 'Coaching mental, psychologie cognitive, ingénierie de processus', 'Stabilise ses peurs par la méditation ou le tri d\'informations', 'Apprendre à ressentir et à exprimer ses émotions plutôt que de chercher à les analyser.'],
            ['RELATIONNEL', 'Hygiène Relationnelle Dominante', 'La qualité de vos relations amicales et intimes est le centre de votre équilibre vital.', 'Échange authentique, bienveillance du cercle d\'amis proches, sens des limites', 'Sensibilité aux dynamiques négatives, absorption des émotions d\'autrui', 'S\'entourer de lumière pour grandir et partager avec le coeur.', 'Chaleureux, soutenant, attentif à l\'équilibre relationnel', 'Conseil relationnel, médiation, travail en milieu d\'aide ou d\'éducation', 'S\'apaise en discutant ouvertement et sincèrement avec ses proches', 'Savoir s\'éloigner des relations nocives et exprimer clairement ses limites.'],
            ['SPIRITUEL', 'Hygiène Spirituelle Dominante', 'L\'alignement avec vos valeurs les plus hautes et le sentiment de connexion globale dominent.', 'Intégrité, clarté morale, force d\'âme face aux crises de vie', 'Perte d\'ancrage matériel, tendance à l\'isolement philosophique', 'Vivre chaque instant en cohérence avec sa vérité intime.', 'Idéaliste, cherche des liens d\'âme authentiques, pacificateur', 'Accompagnement éthique, animation de groupes de partage philosophique, écriture', 'S\'apaise par la contemplation, le silence et la reconnexion à la vie', 'Développer des applications concrètes de ses valeurs à travers l\'action quotidienne.'],
            ['RESSOURCES', 'Hygiène des Ressources Dominante', 'La gestion équilibrée de votre temps, de vos moyens matériels et de votre capital d\'énergie.', 'Productivité sereine, gestion de son emploi du temps rigoureuse, efficacité matérielle', 'Obsession de l\'optimisation, peur de perdre son temps ou ses ressources', 'Utiliser judicieusement chaque ressource pour maximiser son impact de vie.', 'Organisé, précis sur les engagements et la gestion commune', 'Planification stratégique, finance personnelle, organisation administrative', 'Canalise l\'anxiette par l\'organisation méthodique des journées', 'S\'accorder des plages de liberté totale sans aucun objectif d\'optimisation.'],
            ['ENVIRONNEMENT', 'Hygiène Environnementale Dominante', 'La qualité de votre espace physique (ordre, bruit, lumière, accès à la nature) est votre axe majeur d\'équilibre.', 'Sensibilité esthétique, capacité à concevoir un espace sain et inspirant', 'Vulnérabilité accrue aux nuisances sonores, visuelles et au désordre', 'L\'espace physique comme miroir et soutien de l\'état intérieur.', 'Soucieux de l\'ordre, du calme et de l\'harmonie dans le foyer partagé', 'Architecture d\'intérieur, paysagisme, ergonomie, gestion de tiers-lieux', 'Se ressource en rangeant, en s\'exposant à la lumière naturelle ou en marchant en forêt', 'Apprendre à tolérer l\'imperfection temporaire de son environnement sans en faire une source d\'anxiété.']
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('HYGIENE')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }
}
