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

        $this->seedMbti($io);
        $this->seedDisc($io);
        $this->seedEnneagramme($io);
        $this->seedBig5($io);
        $this->seedIntelligences($io);
        $this->seedTalent($io);
        $this->seedEquilibre($io);
        $this->seedHygiene($io);

        $this->em->flush();
        $io->success('Personality questions and profiles seeded successfully!');

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MBTI
    // ─────────────────────────────────────────────────────────────────────────
    private function seedMbti(SymfonyStyle $io): void
    {
        $io->section('MBTI Questions...');
        $questions = [
            // [question, category (dim), [answers: text => value]]
            ['Vous préférez passer votre temps libre avec des amis plutôt que seul.', 'E/I',
                [['text' => 'Oui, je me ressource avec les autres', 'value' => 'E'], ['text' => 'Non, je me ressource dans la solitude', 'value' => 'I']]],
            ['Vous vous adaptez facilement à de nouveaux environnements sociaux.', 'E/I',
                [['text' => 'Facilement — j\'aime rencontrer de nouvelles personnes', 'value' => 'E'], ['text' => 'Difficilement — cela m\'épuise', 'value' => 'I']]],
            ['Quand vous résolvez un problème, vous partez de faits concrets.', 'S/N',
                [['text' => 'Oui, je me base sur ce qui est tangible', 'value' => 'S'], ['text' => 'Non, j\'explore d\'abord les possibilités', 'value' => 'N']]],
            ['Vous préférez les instructions précises aux consignes ouvertes.', 'S/N',
                [['text' => 'Oui, la clarté me rassure', 'value' => 'S'], ['text' => 'Non, j\'aime avoir de la liberté d\'interprétation', 'value' => 'N']]],
            ['Lors d\'une décision difficile, la logique prime sur les émotions.', 'T/F',
                [['text' => 'Oui, je raisonne objectivement', 'value' => 'T'], ['text' => 'Non, je tiens compte de l\'impact sur les personnes', 'value' => 'F']]],
            ['Il vous est facile de dire à quelqu\'un qu\'il a tort, même si cela le blesse.', 'T/F',
                [['text' => 'Oui, la vérité est plus importante', 'value' => 'T'], ['text' => 'Non, je cherche à ménager les sensibilités', 'value' => 'F']]],
            ['Vous planifiez vos journées en détail à l\'avance.', 'J/P',
                [['text' => 'Oui, j\'aime savoir à l\'avance ce qui m\'attend', 'value' => 'J'], ['text' => 'Non, je préfère improviser selon l\'humeur', 'value' => 'P']]],
            ['Vous terminez vos projets bien avant la deadline.', 'J/P',
                [['text' => 'Toujours — l\'anticipation me sécurise', 'value' => 'J'], ['text' => 'Rarement — la pression me stimule', 'value' => 'P']]],
            ['Vous préférez les discussions en grand groupe plutôt qu\'en tête-à-tête.', 'E/I',
                [['text' => 'Oui, l\'énergie collective me galvanise', 'value' => 'E'], ['text' => 'Non, les échanges profonds en privé me correspondent mieux', 'value' => 'I']]],
            ['Vous faites confiance à votre intuition plutôt qu\'à des données brutes.', 'S/N',
                [['text' => 'Non, les données sont plus fiables', 'value' => 'S'], ['text' => 'Oui, mon instinct me guide souvent juste', 'value' => 'N']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('MBTI')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder(0);
                $this->em->persist($ans);
            }
        }

        // Profiles
        $profiles = [
            ['INTJ', 'Architecte', 'Stratège visionnaire. Combine imagination et logique pour concrétiser ses ambitions à long terme.', 'Pensée stratégique, indépendance, détermination', 'Peut paraître froid, perfectionniste excessif, mépris de l\'incompétence', 'Architecte du futur — chaque action est calculée pour l\'impact maximum.', 'Sélectif et indépendant. Préfère les connexions intellectuelles profondes et a du mal avec les conventions sociales vides.', 'R&D, ingénierie stratégique, planification financière ou scientifique de haut niveau.', 'Rationnel et analytique. Analyse ses émotions logiquement plutôt que de les vivre spontanément.', 'Développer son intelligence émotionnelle et sa patience face à l\'imprévu ou à l\'illogisme apparent.'],
            ['INFJ', 'Avocat', 'Idéaliste engagé, discret mais déterminé. Recherche constamment le sens profond.', 'Empathie, vision, créativité, dévouement moral', 'Peut s\'épuiser à vouloir aider tout le monde, perfectionnisme, difficulté à recevoir de la critique', 'L\'âme qui voit ce que les autres ne voient pas encore.', 'Profondément empathique et loyal. Cherche des relations authentiques et intimes à l\'abri du bruit superficiel.', 'Métiers du conseil psychologique, de l\'éducation, de l\'écriture ou de l\'humanitaire.', 'Très sensible à l\'atmosphère émotionnelle ambiante. Absorbe le stress des autres comme une éponge.', 'Apprendre à poser des limites claires et à protéger sa propre énergie vitale sans culpabilité.'],
            ['ENFJ', 'Protagoniste', 'Leader naturel qui inspire et mobilise autour d\'une vision commune.', 'Charisme, empathie, leadership, sens de l\'organisation collective', 'Peut négliger ses propres besoins, sur-implication émotionnelle', 'L\'architecte humain — il façonne les autres vers leur meilleur.', 'Extrêmement dévoué, encourageant et à l\'écoute. A tendance à sur-idéaliser ses proches.', 'Enseignement, gestion d\'équipe, politique, relations publiques ou ressources humaines.', 'Ressent le besoin d\'aider et d\'être aimé. Aligne ses émotions sur l\'harmonie du groupe.', 'Accepter que l\'on ne peut pas sauver tout le monde, et prendre du temps régulier de recentrage personnel.'],
            ['ENFP', 'Inspirateur', 'Enthousiaste, créatif, connecte les idées et les personnes avec une énergie communicative.', 'Créativité, énergie, connexions sociales, adaptabilité', 'Peut manquer de suivi et de structure, procrastination, anxiété de choix', 'L\'étincelle qui allume les possibilités.', 'Passionné, expressif et très ouvert. Aime stimuler la créativité et le potentiel de son partenaire.', 'Domaines artistiques, marketing, journalisme, conseil créatif ou entrepreneuriat de démarrage.', 'Vaste éventail d\'émotions vécues intensément. Cherche constamment l\'authenticité personnelle.', 'Développer sa rigueur, terminer ce qui a été commencé et s\'ancrer dans des routines stabilisatrices.'],
            ['INTP', 'Logicien', 'Penseur innovant qui cherche à comprendre les systèmes les plus complexes.', 'Logique, originalité, objectivité, esprit d\'analyse aiguisé', 'Peut négliger l\'aspect émotionnel, propension à la procrastination intellectuelle', 'L\'architecte des idées — là où les systèmes révèlent leurs secrets.', 'Calme, indépendant et direct. Exprime son affection par l\'aide intellectuelle ou le partage d\'idées.', 'Mathématiques, codage, recherche fondamentale, philosophie, stratégie conceptuelle.', 'Détaché et analytique. Peut se sentir mal à l\'aise face à des explosions émotionnelles.', 'Apprendre à reconnaître et exprimer ses émotions, et valoriser le sens pratique au-delà des pures théories.'],
            ['INFP', 'Médiateur', 'Poétique, gentil et altruiste. Toujours désireux d\'aider une bonne cause et de promouvoir l\'harmonie.', 'Altruisme, loyauté, créativité poétique, sensibilité fine', 'Trop idéaliste, a du mal avec les données brutes et les tâches répétitives, prend tout personnellement', 'Le rêveur discret qui cherche à guérir le monde par la douceur.', 'Profondément dévoué et romantique. Cherche une âme sœur partageant ses valeurs intimes élevées.', 'Écriture, arts, psychothérapie, conseil en orientation de vie ou action caritative.', 'Monde émotionnel intérieur extrêmement riche et nuancé. Cache souvent son intensité sous un calme apparent.', 'Apprendre à accepter les réalités matérielles et pragmatiques, et s\'affirmer face aux conflits nécessaires.'],
            ['ENTJ', 'Commandant', 'Leader naturel avec un fort sens de la stratégie et de l\'efficacité.', 'Décision, vision stratégique, efficacité, charisme naturel', 'Peut être perçu comme autoritaire ou insensible, intolérance aux inefficacités', 'Le général qui transforme les défis en victoires concrètes.', 'Exigeant, direct mais protecteur. Encourage la croissance de ses proches avec vigueur.', 'Direction d\'entreprise, direction de projet d\'envergure, droit des affaires, conseil en réorganisation.', 'Focalisé sur la rationalité et la performance. Réprime la tristesse et la peur sous forme de colère ou d\'hyperactivité.', 'Pratiquer la patience, apprendre à déléguer sans micro-manager, et développer une écoute empathique.'],
            ['ENTP', 'Innovateur', 'Débatteur ingénieux qui adore analyser et remettre en question les systèmes.', 'Innovation, esprit vif de débat, adaptabilité, enthousiasme conceptuel', 'Peut manquer de constance, tendance à la provocation intellectuelle, lassitude rapide', 'L\'intellectuel du possible — challenge tout ce qui existe.', 'Stimulant, aime débattre et jouer avec les idées. A horreur des routines et de la rigidité relationnelle.', 'Conseil en innovation, développement produit, entrepreneuriat, stratégie de marque.', 'Énergisé par le jeu intellectuel. Utilise l\'humour ou le cynisme comme bouclier émotionnel.', 'Apprendre à se stabiliser, s\'engager sur la durée et respecter les sentiments et les besoins de sécurité des autres.'],
            ['ISTJ', 'Logisticien', 'Fiable et méthodique. Pilier de stabilité dans tout environnement.', 'Fiabilité, méthode rigoureuse, sens du devoir, intégrité factuelle', 'Peut résister au changement, difficulty à comprendre les comportements impulsifs', 'La fondation sur laquelle les autres peuvent s\'appuyer en toute confiance.', 'Loyal, dévoué et pudique. Montre son amour par des actions concrètes et le respect des engagements.', 'Comptabilité, administration publique, gestion de base de données, logistique industrielle ou militaire.', 'Calme, contrôlé et stable. Évite de faire des scènes ou d\'étaler ses sentiments en public.', 'S\'ouvrir aux nouvelles idées sans méfiance préalable, et accepter l\'expression émotionnelle de son entourage.'],
            ['ISFJ', 'Défenseur', 'Protecteur dévoué qui soutient discrètement les autres avec une loyauté remarquable.', 'Loyauté, attention méticuleuse, mémoire des détails, sens aigu du service', 'Difficulté à dire non, tendance à surcharger son emploi du temps, peur de s\'affirmer', 'Le gardien silencieux du bien-être et de l\'harmonie des autres.', 'Attentionné, loyal et profondément attentionné. Fait tout pour plaire et préserver la paix.', 'Soins infirmiers, travail social, secrétariat de direction, enseignement primaire, assistance clientèle.', 'Répressif de ses propres besoins au nom du bien-être d\'autrui. Accumule la fatigue en silence.', 'Apprendre à poser des limites fermes, s\'accorder de l\'importance et s\'affirmer sans culpabiliser.'],
            ['ESTJ', 'Directeur', 'Administrateur efficace qui crée de l\'ordre et structure son environnement.', 'Organisation méthodique, leadership fort, droiture morale, efficacité', 'Rigidité d\'esprit, tendance à juger hâtivement, manque de tact', 'L\'architecte de l\'ordre opérationnel et de la clarté.', 'Structuré, traditionnel et direct. Veille au respect des règles familiales et à la sécurité du foyer.', 'Management opérationnel, direction de production, administration générale, gestion financière d\'entité.', 'Valorise la stabilité et le devoir. Gère le stress par le travail acharné et le renforcement des règles.', 'Apprendre à faire confiance aux solutions informelles, s\'ouvrir à la sensibilité et cultiver la flexibilité intellectuelle.'],
            ['ESFJ', 'Consul', 'Nourrit les connexions et veille au bien-être de sa communauté.', 'Empathie chaleureuse, sens inné du service, sociabilité accueillante', 'Dépendance excessive à l\'approbation externe, évitement viscéral du conflit', 'Le ciment social — unit, rassemble et prend soin de chacun.', 'Fusionnel, généreux et décrié. Attend beaucoup de gratitude et de validation en retour de ses attentions.', 'Hôtellerie, ressources humaines d\'accueil, animation d\'événements, soins ou enseignement.', 'Sensible au jugement des autres. Calme son anxiété en s\'assurant que tout le monde est heureux autour de lui.', 'Apprendre à s\'auto-valider, accepter le conflit constructif et tolérer que certains ne partagent pas ses valeurs.'],
            ['ISTP', 'Virtuose', 'Expérimentateur pragmatique. Excelle dans la résolution de problèmes concrets.', 'Pragmatisme, adaptabilité instantanée, logique instrumentale, calme face aux imprévus', 'Peut paraître détaché ou secret, réticence face aux règles rigides, prise de risque physique', 'L\'artisan calme qui comprend comment chaque objet fonctionne.', 'Indépendant, discret. A besoin de liberté spatiale et montre son attachement par la résolution de soucis pratiques.', 'Mécanique, ingénierie de terrain, secourisme, pilotage, artisanat d\'art de précision.', 'Très intériorisé. S\'isole pour régler ses soucis et n\'aime pas être forcé à parler de son monde intérieur.', 'Communiquer davantage ses intentions de façon proactive, et accorder de l\'attention aux besoins émotionnels de ses proches.'],
            ['ISFP', 'Aventurier', 'Artiste sensible à l\'affût de nouvelles expériences esthétiques.', 'Créativité plastique, sensibilité artistique fine, flexibilité d\'agenda, bienveillance discrète', 'Difficulté à planifier à long terme, évitement systématique des confrontations, vulnérabilité émotionnelle', 'L\'artiste discret qui vit et s\'exprime pleinement dans l\'instant présent.', 'Doux, tolérant et romantique. A besoin d\'un partenaire respectueux de sa liberté d\'expression intime.', 'Métiers de la mode, art graphique, photographie, horticulture ou soins animaliers.', 'Ressenti profond mais secret. Exprime son intériorité par ses créations plutôt que par des discours.', 'Apprendre à affronter les conflits nécessaires et à structurer des objectifs à long terme.'],
            ['ESTP', 'Entrepreneur', 'Dynamique et perspicace. Agit d\'abord, pense ensuite.', 'Énergie débordante, sens aigu de l\'observation concrète, charme persuasif, résilience physique', 'Impatience face aux théories abstraites, prise de risque excessive, difficulté à se poser', 'L\'agent de changement actif qui résout les crises en direct.', 'Enthousiaste, dynamique et extraverti. Préfère les activités partagées plutôt que les longues discussions psychologiques.', 'Négociation commerciale, entrepreneuriat de terrain, courtage, sécurité ou secourisme d\'élite.', 'Ancré dans le physique et l\'action. Ignore ou minimise les blessures émotionnelles pour continuer d\'avancer.', 'Réfléchir aux conséquences à long terme de ses décisions et cultiver l\'empathie face à la fragilité émotionnelle.'],
            ['ESFP', 'Amuseur', 'Spontané et plein d\'énergie. Transforme chaque moment en expérience mémorable.', 'Spontanéité créative, enthousiasme communicatif, sens inné de la scène, joie de vivre', 'Manque de structure, évitement des tâches répétitives, propension à se disperser financièrement ou temporellement', 'L\'énergie pure qui redonne le sourire et anime la vie.', 'Expressif, aimant et très démonstratif. Recherche le plaisir partagé et s\'adaptate joyeusement à son entourage.', 'Métiers de la scène, animation culturelle, relations publiques d\'accueil, commerce de détail, conseil touristique.', 'Vit ses émotions au jour le jour avec spontanéité. Évite activement la tristesse ou le deuil en s\'entourant d\'activité.', 'Développer son autodiscipline, faire face aux responsabilités administratives et financières rébarbatives.'],
        ];

        // Use unique codes
        $seenCodes = [];
        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            if (in_array($code, $seenCodes)) continue;
            $seenCodes[] = $code;
            $p = (new PersonalityProfile())
                ->setTestType('MBTI')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISC
    // ─────────────────────────────────────────────────────────────────────────
    private function seedDisc(SymfonyStyle $io): void
    {
        $io->section('DISC Questions...');
        $questions = [
            ['Dans un groupe, vous prenez naturellement les rênes.', 'style',
                [['text' => 'Toujours — je dirige instinctivement', 'value' => 'D'], ['text' => 'Souvent — j\'aime motiver les autres', 'value' => 'I'], ['text' => 'Parfois — si personne d\'autre ne le fait', 'value' => 'S'], ['text' => 'Rarement — je préfère analyser avant d\'agir', 'value' => 'C']]],
            ['Face à un défi, votre première réaction est de :', 'style',
                [['text' => 'Décider rapidement et agir', 'value' => 'D'], ['text' => 'Rallier les autres à la solution', 'value' => 'I'], ['text' => 'Évaluer calmement les options', 'value' => 'S'], ['text' => 'Analyser en détail tous les scénarios', 'value' => 'C']]],
            ['On vous décrit souvent comme quelqu\'un de :', 'style',
                [['text' => 'Direct et franc', 'value' => 'D'], ['text' => 'Enthousiaste et sociable', 'value' => 'I'], ['text' => 'Patient et stable', 'value' => 'S'], ['text' => 'Précis et rigoureux', 'value' => 'C']]],
            ['Dans votre travail, vous êtes davantage motivé par :', 'style',
                [['text' => 'Atteindre les objectifs coûte que coûte', 'value' => 'D'], ['text' => 'L\'interaction et la reconnaissance des autres', 'value' => 'I'], ['text' => 'La stabilité et les relations durables', 'value' => 'S'], ['text' => 'La qualité et la précision du travail', 'value' => 'C']]],
            ['Sous pression, vous avez tendance à :', 'style',
                [['text' => 'Devenir plus agressif et exigeant', 'value' => 'D'], ['text' => 'Devenir plus impulsif et désorganisé', 'value' => 'I'], ['text' => 'Résister passivement au changement', 'value' => 'S'], ['text' => 'Devenir hypercritique et paralysé', 'value' => 'C']]],
            ['Votre environnement de travail idéal est :', 'style',
                [['text' => 'Compétitif, orienté résultats', 'value' => 'D'], ['text' => 'Créatif, collaboratif et dynamique', 'value' => 'I'], ['text' => 'Stable, prévisible et harmonieux', 'value' => 'S'], ['text' => 'Structuré avec des processus clairs', 'value' => 'C']]],
            ['Quand quelqu\'un présente une idée, vous :', 'style',
                [['text' => 'Défiez immédiatement pour tester sa solidité', 'value' => 'D'], ['text' => 'L\'accueillez avec enthousiasme', 'value' => 'I'], ['text' => 'L\'écoutez attentivement avant de répondre', 'value' => 'S'], ['text' => 'Demandez des données et des preuves', 'value' => 'C']]],
            ['Votre plus grande peur professionnelle est :', 'style',
                [['text' => 'Perdre le contrôle ou être exploité', 'value' => 'D'], ['text' => 'Être rejeté ou ignoré', 'value' => 'I'], ['text' => 'Vivre trop d\'instabilité et de changements', 'value' => 'S'], ['text' => 'Être critiqué pour des erreurs ou un travail bâclé', 'value' => 'C']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('DISC')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        $profiles = [
            ['D', 'Le Décideur (Dominant)', 'Vous êtes direct, décidé, énergique et orienté vers les résultats. Vous aimez relever des défis et surmonter les obstacles.', 'Leadership, décision rapide, efficacité, autonomie', 'Peut paraître impatient, autoritaire ou insensible sous pression', 'Besoin de contrôle et d\'impact direct sur les événements.', 'Direct, va droit au but, communication concise', 'Postes de direction, d\'entrepreneuriat ou gestion de projet à fort enjeu', 'Canalise l\'anxiété sous forme d\'action immédiate ou d\'impatience', 'Développer l\'écoute active et l\'empathie envers les autres.'],
            ['I', 'L\'Influenceur', 'Enthousiaste, chaleureux, persuasif et très sociable. Vous aimez connecter avec les gens et inspirer le collectif.', 'Communication, optimisme, motivation d\'équipe, créativité', 'Difficulté à se concentrer sur les détails administratifs, impulsivité', 'Recherche de l\'approbation sociale, de la nouveauté et du plaisir partagé.', 'Chaleureux, expressif, aime raconter des histoires et motiver', 'Ventes, marketing, relations publiques, animation ou enseignement', 'Exprime ses émotions avec vivacité et redoute le rejet', 'Améliorer le suivi des projets, la gestion du temps et l\'autodiscipline.'],
            ['S', 'Le Stabilisateur', 'Patient, fiable, persévérant et soucieux de l\'harmonie collective. Vous êtes le pilier calme sur lequel le groupe peut compter.', 'Loyauté, écoute, soutien constant, méthodologie fiable', 'Résistance au changement, évitement des conflits directs', 'Besoin de sécurité, de paix intérieure et de relations prévisibles.', 'Calme, attentif, constructif et axé sur le compromis', 'Ressources humaines, support technique, administration, conseil ou logistique', 'Garde ses tensions en interne et recherche la sérénité à tout prix', 'Apprendre à dire non, à s\'affirmer et à accueillir le changement.'],
            ['C', 'Le Consciencieux', 'Analytique, précis, rigoureux et attaché aux faits et aux procédures. Vous valorisez le travail de qualité et la vérité objective.', 'Rigueur, attention aux détails, critique constructive, standards de qualité', 'Tendance au perfectionnisme paralysant, méfiance sous pression', 'Besoin d\'exactitude, de structure et d\'éviter les erreurs.', 'Factuel, formel, précis, préfère les écrits argumentés', 'Ingénierie, finance, audit, recherche scientifique ou conformité légale', 'Intériorise ses doutes et rationalise les émotions pour les contrôler', 'Accepter l\'imperfection, lâcher prise et faire confiance à son intuition.']
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('DISC')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENNEAGRAMME
    // ─────────────────────────────────────────────────────────────────────────
    private function seedEnneagramme(SymfonyStyle $io): void
    {
        $io->section('Ennéagramme Questions...');
        $questions = [
            ['Je me sens souvent obligé de corriger ce qui est mal fait autour de moi.', 'type',
                [['text' => 'Très souvent', 'value' => '1'], ['text' => 'Parfois', 'value' => '4'], ['text' => 'Rarement', 'value' => '7']]],
            ['J\'ai besoin de me sentir utile et apprécié par mes proches.', 'type',
                [['text' => 'Très souvent', 'value' => '2'], ['text' => 'Parfois', 'value' => '6'], ['text' => 'Rarement', 'value' => '5']]],
            ['Mon image de succès et de compétence est très importante pour moi.', 'type',
                [['text' => 'Très souvent', 'value' => '3'], ['text' => 'Parfois', 'value' => '1'], ['text' => 'Rarement', 'value' => '9']]],
            ['Je me sens unique et différent des autres — cela peut me rendre mélancolique.', 'type',
                [['text' => 'Très souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '2'], ['text' => 'Rarement', 'value' => '3']]],
            ['Je préfère observer avant de participer et j\'aime avoir de l\'espace seul.', 'type',
                [['text' => 'Très souvent', 'value' => '5'], ['text' => 'Parfois', 'value' => '9'], ['text' => 'Rarement', 'value' => '7']]],
            ['J\'ai souvent besoin de m\'assurer que les personnes autour de moi sont fiables.', 'type',
                [['text' => 'Très souvent', 'value' => '6'], ['text' => 'Parfois', 'value' => '1'], ['text' => 'Rarement', 'value' => '8']]],
            ['Je cherche constamment de nouvelles expériences pour rester stimulé.', 'type',
                [['text' => 'Très souvent', 'value' => '7'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '5']]],
            ['Je n\'accepte pas qu\'on me contrôle ou qu\'on me pousse dans mes retranchements.', 'type',
                [['text' => 'Très souvent', 'value' => '8'], ['text' => 'Parfois', 'value' => '6'], ['text' => 'Rarement', 'value' => '2']]],
            ['Je préfère éviter les conflits et garder l\'harmonie autour de moi.', 'type',
                [['text' => 'Très souvent', 'value' => '9'], ['text' => 'Parfois', 'value' => '2'], ['text' => 'Rarement', 'value' => '8']]],
            ['Mon pire cauchemar serait d\'être perçu comme imparfait ou incompétent.', 'type',
                [['text' => 'Imparfait (perfectionnisme)', 'value' => '1'], ['text' => 'Inutile (besoin d\'aider)', 'value' => '2'], ['text' => 'Incompétent (image)', 'value' => '3']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('ENNEAGRAMME')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        // Profiles
        $profiles = [
            ['1', 'Le Réformateur', 'Éthique, idéaliste et perfectionniste. Il veut faire les choses correctement.', 'Intégrité, organisation, sens du devoir, discipline', 'Rigidité, autocritique excessive, intolérance à l\'erreur', 'Je m\'améliore en acceptant que le bien suffise.', 'Attentif, loyal, mais exigeant. Attend des autres le même niveau de perfection morale et d\'effort.', 'Métiers exigeant de la rigueur, de la déontologie ou du contrôle qualité (audit, enseignement, droit, gestion).', 'Réprime la colère sous forme d\'irritation contenue ou d\'indignation morale. Lutte constante entre devoir et désirs.', 'Cultiver l\'indulgence, accepter l\'imperfection comme faisant partie de la nature humaine, s\'accorder du repos.'],
            ['2', 'L\'Altruiste', 'Généreux, caring et interpersonnel. Il a besoin d\'être aimé et apprécié.', 'Générosité, empathie, relationnel, soutien inconditionnel', 'Dépendance affective, négligence de ses propres besoins, manipulation inconsciente', 'Je donne sans m\'oublier.', 'Chaleureux, fusionnel. Peut étouffer les autres avec ses attentions ou attendre une réciprocité non exprimée.', 'Métiers du soin, du social, du conseil, des relations publiques ou de l\'enseignement.', 'Fierté d\'être indispensable aux autres. Projette ses propres besoins sur autrui pour éviter de les affronter lui-même.', 'Apprendre à exprimer directement ses besoins, poser des limites claires et accepter de recevoir sans conditions.'],
            ['3', 'Le Battant', 'Adaptable, ambitieux et orienté image. Il veut réussir et être admiré.', 'Ambition, efficacité, adaptabilité, charisme, orientation résultats', 'Superficialité, surmenage (workaholisme), peur viscérale de l\'échec', 'Mon succès ne définit pas ma valeur.', 'Charismatique, cherche à plaire et à impressionner. Peut masquer sa vulnérabilité derrière un masque professionnel.', 'Entrepreneuriat, vente, management, marketing, conseil stratégique ou communication.', 'Identification totale à son travail et à son statut social. Refoulement des émotions pour rester performant.', 'Se connecter à ses sentiments réels, ralentir le rythme, accepter la vulnérabilité et valoriser l\'être plutôt que le faire.'],
            ['4', 'L\'Individualiste', 'Sensible, créatif et introspectif. Il veut se sentir unique.', 'Créativité, profondeur émotionnelle, originalité, empathie profonde', 'Mélancolie, envie excessive, sentiment d\'incompréhension ou de rejet', 'Mon unicité est ma force, non mon isolement.', 'Intense et passionné. Recherche des connexions authentiques mais peut rejeter les autres par peur d\'être déçu.', 'Métiers artistiques, psychologie, écriture, design, conseil en image ou thérapie.', 'Tendance à dramatiser ou à romantiser la souffrance. Nostalgie constante de ce qui manque ou de l\'idéal inaccessible.', 'S\'ancrer dans le quotidien pratique, stabiliser ses humeurs par la discipline, et apprécier le présent sans idéalisation mélancolique.'],
            ['5', 'L\'Investigateur', 'Analytique, perceptif et discret. Il veut comprendre le monde.', 'Expertise, objectivité, autonomie, capacité de concentration élevée', 'Détachement excessif, isolation intellectuelle, avarice de son temps et de son énergie', 'Le savoir s\'enrichit dans le partage.', 'Indépendant, réservé. Préfère les échanges intellectuels ou les petits groupes et a besoin de beaucoup de temps de solitude.', 'Recherche scientifique, analyse de données, ingénierie, expertise technique ou philosophie.', 'Peur d\'être submergé par les exigences émotionnelles d\'autrui. Accumulation d\'informations comme mécanisme de sécurité.', 'Partager ses connaissances, s\'engager activement dans les relations physiques et émotionnelles, et faire confiance à son corps.'],
            ['6', 'Le Loyaliste', 'Engagé, fiable mais anxieux. Il cherche la sécurité et la confiance.', 'Loyauté, fiabilité, persévérance, sens du collectif, vigilance face aux risques', 'Anxiété chronique, doute excessif, méfiance ou rébellion défensive', 'Ma sécurité vient de l\'intérieur.', 'Très loyal une fois la confiance établie, mais teste souvent la fiabilité des autres avant de s\'ouvrir.', 'Métiers de la conformité, de la gestion des risques, de la sécurité, ou travail en équipe dans de grandes organisations stables.', 'Vigilance constante face aux menaces potentielles. Tiraillement entre le besoin d\'autorité et la peur d\'être contrôlé.', 'Développer la confiance en soi et en la vie, calmer le mental par l\'action pragmatique, et cesser de sur-analyser les scénarios catastrophes.'],
            ['7', 'L\'Enthousiaste', 'Spontané, curieux et optimiste. Il veut tout vivre et évite la douleur.', 'Optimisme, créativité, énergie communicative, vision globale, adaptabilité', 'Dispersion mentale, évitement de la souffrance profonde, manque de persévérance', 'La profondeur se trouve aussi dans l\'immobilité.', 'Jovial, stimulant, cherche à divertir et à éviter les drames relationnels ou les conversations trop lourdes.', 'Innovation, planification de projets créatifs, voyages, événementiel ou conseil en démarrage de projet.', 'Gloutonnerie d\'expériences et d\'options futures pour masquer l\'anxiété interne ou la tristesse latente.', 'Se focaliser sur un projet à la fois, accueillir les émotions désagréables, et apprendre à vivre le silence et l\'ennui.'],
            ['8', 'Le Challenger', 'Puissant, décisif et protecteur. Il refuse d\'être contrôlé.', 'Leadership, courage, authenticité, esprit de justice, force de protection', 'Agressivité involontaire, excès de contrôle, intolérance à la mollesse', 'La vraie force accueille la vulnérabilité.', 'Protecteur et direct. Exige une franchise totale et n\'hésite pas à entrer en confrontation pour clarifier les rapports de force.', 'Direction d\'entreprise, entrepreneuriat, gestion de crise, droit pénal, syndicalisme ou sécurité.', 'Refoulement de la vulnérabilité sous forme de colère ou d\'affirmation de puissance. Intensité d\'action élevée.', 'Apprendre à exprimer sa propre vulnérabilité, écouter les autres sans imposer sa force, et cultiver la douceur et la patience.'],
            ['9', 'Le Pacificateur', 'Réceptif, reassurant et stable. Il veut maintenir la paix intérieure.', 'Diplomatie, empathie universelle, stabilité d\'humeur, écoute impartiale', 'Inertie, oubli de soi, procrastination par évitement des choix', 'Ma voix et mes besoins comptent autant que ceux des autres.', 'Agréable et conciliant. S\'adapte facilement aux désirs des autres au détriment de ses propres opinions.', 'Médiation, ressources humaines, administration générale, thérapie de couple ou conseil en conciliation.', 'Colère endormie ou passive-agressive. Utilisation de distractions routinières pour éviter de se confronter aux conflits de vie.', 'S\'affirmer clairement, prendre position, agir avec détermination sur ses priorités personnelles, et embrasser le conflit constructif.'],
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('ENNEAGRAMME')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BIG5
    // ─────────────────────────────────────────────────────────────────────────
    private function seedBig5(SymfonyStyle $io): void
    {
        $io->section('Big Five Questions...');
        $questions = [
            ['J\'aime explorer de nouvelles idées et expériences inédites.', 'O',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je planifie tout soigneusement avant d\'agir.', 'C',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je préfère être entouré de gens plutôt que seul.', 'E',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je fais confiance aux gens facilement et je me montre coopératif.', 'A',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je me sens souvent anxieux ou stressé face aux imprévus.', 'N',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['La routine et la répétition ne m\'effraient pas.', 'C',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je suis souvent la personne qui lance les conversations.', 'E',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je trouve facilement de l\'intérêt dans des sujets très variés.', 'O',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Je préfère éviter les conflits même si j\'ai raison.', 'A',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
            ['Mes humeurs changent souvent et rapidement.', 'N',
                [['text' => 'Tout à fait d\'accord', 'value' => '5'], ['text' => 'Plutôt d\'accord', 'value' => '4'], ['text' => 'Neutre', 'value' => '3'], ['text' => 'Plutôt pas d\'accord', 'value' => '2'], ['text' => 'Pas du tout d\'accord', 'value' => '1']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('BIG5')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        $profiles = [
            ['O', 'Ouverture d\'Esprit Élevée', 'Votre profil se distingue par une grande curiosité intellectuelle, une forte imagination et un attrait pour l\'originalité.', 'Créativité, curiosité intellectuelle, vision large, adaptabilité conceptuelle', 'Dispersion intellectuelle, difficulté avec la routine stricte', 'Recherche constante de nouveauté mentale et esthétique.', 'Ouvert au débat d\'idées, curieux des perspectives de l\'autre', 'Recherche, arts, design, stratégie d\'entreprise ou innovation', 'Sensible à l\'art et aux nuances, réactions émotionnelles riches', 'Structurer ses idées par des actions tangibles et concrétiser ses visions.'],
            ['C', 'Conscienciosité Élevée', 'Vous faites preuve d\'autodiscipline, de méthode, de sens du devoir et d\'une forte orientation vers la réussite.', 'Organisation, rigueur, fiabilité, persévérance face à l\'effort', 'Rigidité mentale, risque de surmenage, perfectionnisme excessif', 'Besoin d\'ordre, de contrôle et d\'accomplissement de sa mission.', 'Responsable, tient ses promesses, attend le même sérieux en retour', 'Management opérationnel, gestion de projets complexes, logistique', 'Calme et résistant mais peut accumuler une pression sourde', 'Accorder plus de place à la spontanéité, au repos et à l\'imprévu.'],
            ['E', 'Extraversion Élevée', 'Vous tirez votre énergie des interactions sociales, de l\'action extérieure et des stimulations environnementales.', 'Sociabilité, dynamisme, leadership spontané, enthousiasme', 'Difficulté avec la solitude prolongée, besoin d\'approbation externe', 'Recherche de stimulations et de connexions avec l\'extérieur.', 'Expressif, chaleureux, initiateur de moments de partage', 'Relations clients, événementiel, négociation, animation d\'équipes', 'Optimiste et démonstratif dans l\'expression de ses ressentis', 'Cultiver l\'introspection, le silence et l\'écoute de son intériorité.'],
            ['A', 'Agréabilité Élevée', 'Vous accordez une grande importance à l\'harmonie sociale, à l\'empathie, à la confiance et à la coopération.', 'Altruisme, compassion, esprit d\'équipe, bienveillance naturelle', 'Difficulté à s\'affirmer face aux conflits, risque d\'abus par autrui', 'Désir sincère de maintenir l\'harmonie et d\'aider les autres.', 'Soutenant, à l\'écoute, évite les jugements hâtifs', 'Métiers du soin, enseignement, médiation, action humanitaire', 'Sensible à la souffrance d\'autrui, cherche à apaiser les tensions', 'Développer son assertivité, savoir poser des limites claires et fermes.'],
            ['N', 'Sensibilité Émotionnelle Élevée', 'Vous possédez une grande réactivité émotionnelle et une vigilance accrue face aux risques potentiels.', 'Haute réceptivité aux détails, intuition, conscience des dangers', 'Vulnérabilité au stress, tendance à s\'inquiéter de façon excessive', 'Recherche de sécurité émotionnelle et de stabilité.', 'Besoin de réassurance, profond mais parfois réactif ou anxieux', 'Domaines demandant de la nuance, du conseil ou de la gestion des risques', 'Fluctuations rapides d\'humeur, ressentis profonds', 'Développer des techniques de régulation du stress (méditation, sport).']
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('BIG5')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INTELLIGENCES MULTIPLES
    // ─────────────────────────────────────────────────────────────────────────
    private function seedIntelligences(SymfonyStyle $io): void
    {
        $io->section('Intelligences Multiples Questions...');
        $questions = [
            ['J\'apprends mieux en lisant et en écrivant plutôt qu\'en regardant ou en faisant.', 'linguistique',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je pense souvent en nombres et en schémas logiques.', 'logique',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je visualise facilement les objets dans l\'espace.', 'spatiale',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je retiens facilement les mélodies et je remarque les sons environnants.', 'musicale',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['J\'apprends mieux en faisant — manipulation, sport, mouvement.', 'kinesthesique',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je suis sensible à la nature, aux animaux et à l\'environnement.', 'naturaliste',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je comprends facilement les motivations et les émotions des autres.', 'interpersonnelle',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Je me connais très bien et je comprends ce qui me motive.', 'intrapersonnelle',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('INTELLIGENCES')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }

        $profiles = [
            ['LINGUISTIQUE', 'Dominante Intelligence Linguistique', 'Sensibilité accrue aux mots, à la grammaire, au rythme des phrases et à la narration écrite et parlée.', 'Expression verbale, écriture, mémoire des mots, argumentation', 'Tendance à trop rationaliser ou sur-analyser verbalement', 'Amour profond des histoires, de la lecture et du débat d\'idées.', 'Clair, à l\'écoute des mots choisis, aime convaincre', 'Écriture, journalisme, enseignement, droit, traduction', 'Calme son mental en mettant ses pensées par écrit', 'Développer sa capacité d\'écoute non-verbale et observer le langage corporel.'],
            ['LOGIQUE', 'Dominante Intelligence Logique', 'Capacité à analyser logiquement les problèmes, à résoudre des équations complexes et à raisonner scientifiquement.', 'Raisonnement abstrait, analyse de données, résolution de problèmes', 'Manque de patience face à l\'illogisme ou aux émotions irrationnelles', 'Recherche de la cause logique et de la vérité derrière chaque fait.', 'Factuel, rationnel, argumenté, cherche la cohérence', 'Ingénierie, finance, développement informatique, sciences exactes', 'Canalise ses ressentis sous forme d\'analyse structurelle', 'Apprendre à accueillir le flou, le non-rationnel et l\'expression émotionnelle.'],
            ['SPATIALE', 'Dominante Intelligence Spatiale', 'Aptitude à se représenter mentalement l\'espace, à manipuler des images en trois dimensions et à s\'orienter.', 'Visualisation mentale, orientation géographique, conception graphique, créativité visuelle', 'Difficulté à expliquer verbalement des concepts visuels complexes', 'Penser en images et percevoir les architectures subtiles de l\'espace.', 'Préfère les supports visuels et les métaphores graphiques', 'Architecture, design, cartographie, arts plastiques, chirurgie', 'Se ressource en créant ou en modifiant son environnement visuel', 'Pratiquer la verbalisation méthodique de ses représentations intérieures.'],
            ['MUSICALE', 'Dominante Intelligence Musicale', 'Sensibilité innée aux sons, au rythme, aux mélodies et à la structure harmonique de l\'environnement.', 'Sens du rythme, oreille musicale développée, expression par le son', 'Surcharge émotionnelle ou cognitive en milieu bruyant', 'Vibrer en harmonie avec les sons et ressentir l\'univers sur le plan acoustique.', 'Sensible aux intonations de voix, aux silences et à l\'harmonie sonore', 'Musique, composition, ingénierie du son, thérapie par l\'art', 'Régule ses humeurs et ses émotions par l\'écoute ou la pratique musicale', 'Intégrer le silence conscient et le repos acoustique dans sa journée.'],
            ['KINESTHESIQUE', 'Dominante Intelligence Corporelle', 'Capacité à utiliser son corps pour exprimer des idées ou des sentiments, et habileté dans le travail manuel.', 'Coordination motrice, dextérité manuelle, mémoire corporelle physique', 'Besoin de bouger constamment, inconfort dans la passivité assise', 'Se connecter au monde et intégrer les concepts par le toucher et le mouvement.', 'Expressif physiquement, a besoin de proximité spatiale', 'Métiers manuels, chirurgie, sport, danse, artisanat d\'art', 'Régule son stress par le mouvement physique et le sport', 'Pratiquer la méditation de pleine conscience ancrée dans les sensations du corps.'],
            ['NATURALISTE', 'Dominante Intelligence Naturaliste', 'Sensibilité forte à la nature, capacité à classifier les espèces et à comprendre les équilibres écologiques.', 'Observation fine, classification, patience, harmonie avec le vivant', 'Se sent étouffé ou stressé en milieu urbain dense et déconnecté', 'Recherche constante de communion et d\'immersion dans le monde vivant.', 'Simple, authentique, connecté aux besoins fondamentaux', 'Agriculture, écologie, biologie, métiers animaliers, herboristerie', 'Trouve l\'apaisement immédiat au contact des arbres et des espaces naturels', 'Apprendre à transposer la patience naturelle à la gestion des relations humaines.'],
            ['INTERPERSONNELLE', 'Dominante Intelligence Interpersonnelle', 'Capacité à comprendre les autres, à percevoir leurs intentions, leurs émotions et à interagir avec fluidité.', 'Empathie active, leadership bienveillant, communication, médiation de conflits', 'Tendance à s\'oublier au profit du groupe ou à être influencé', 'Créer de l\'harmonie, du lien et de l\'unité entre les individus.', 'Chaleureux, diplomate, à l\'écoute des besoins profonds d\'autrui', 'Ressources humaines, psychologie, vente, éducation, management', 'S\'apaise en échangeant avec ses pairs et en aidant autrui', 'Se réserver des moments de solitude pour se reconnecter à son propre centre.'],
            ['INTRAPERSONNELLE', 'Dominante Intelligence Intrapersonnelle', 'Connaissance approfondie de soi-même, de ses forces, de ses limites, de ses valeurs et de ses motivations.', 'Autonomie décisionnelle, introspection, alignement de valeurs, résilience', 'Tendance à l\'isolement ou au repli sur soi en cas de crise', 'Vivre en parfaite cohérence avec son intériorité profonde.', 'Indépendant, sincère, peu enclin aux artifices sociaux', 'Philosophie, psychologie, recherche, entrepreneuriat individuel', 'Gère les crises en s\'isolant pour analyser ses émotions en profondeur', 'S\'ouvrir davantage aux partages authentiques avec son entourage proche.']
        ];

        foreach ($profiles as [$code, $title, $desc, $strengths, $weaknesses, $essence, $relationships, $career, $emotionalDynamics, $growthAxes]) {
            $p = (new PersonalityProfile())
                ->setTestType('INTELLIGENCES')->setCode($code)->setTitle($title)
                ->setDescription($desc)->setStrengths($strengths)->setWeaknesses($weaknesses)
                ->setEssence($essence)->setRelationships($relationships)->setCareer($career)
                ->setEmotionalDynamics($emotionalDynamics)->setGrowthAxes($growthAxes);
            $this->em->persist($p);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TALENT
    // ─────────────────────────────────────────────────────────────────────────
    private function seedTalent(SymfonyStyle $io): void
    {
        $io->section('Talent Questions...');
        $questions = [
            ['Quand vous démarrez quelque chose de nouveau, vous le faites naturellement et sans effort.', 'spontane',
                [['text' => 'Tout à fait — cela coule de source', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Sous pression, vous trouvez des solutions créatives rapidement.', 'pression',
                [['text' => 'Tout à fait', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Jamais', 'value' => '1']]],
            ['Certaines activités vous donnent de l\'énergie même après des heures de travail.', 'plaisir',
                [['text' => 'Oui, clairement', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Rarement ou jamais', 'value' => '1']]],
            ['Vos proches vous font souvent remarquer que vous êtes particulièrement doué pour quelque chose.', 'spontane',
                [['text' => 'Souvent et sur plusieurs points', 'value' => '5'], ['text' => 'Quelquefois', 'value' => '3'], ['text' => 'Rarement', 'value' => '1']]],
            ['Quand tout semble s\'effondrer autour de vous, vous restez centré et efficace.', 'pression',
                [['text' => 'Toujours — la pression me libère', 'value' => '5'], ['text' => 'Souvent', 'value' => '4'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Rarement', 'value' => '2'], ['text' => 'Je me décompose sous la pression', 'value' => '1']]],
            ['Il y a des domaines où vous entrez dans un état de "flow" total.', 'plaisir',
                [['text' => 'Oui, régulièrement', 'value' => '5'], ['text' => 'Parfois', 'value' => '3'], ['text' => 'Très rarement', 'value' => '1']]],
        ];

        foreach ($questions as $i => [$text, $cat, $answers]) {
            $q = (new PersonalityQuestion())->setTestType('TALENT')->setCategory($cat)->setQuestion($text)->setSortOrder($i + 1);
            $this->em->persist($q);
            foreach ($answers as $j => $a) {
                $ans = (new PersonalityAnswer())->setQuestion($q)->setText($a['text'])->setValue($a['value'])->setSortOrder($j);
                $this->em->persist($ans);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EQUILIBRE DE VIE (Wheel of Life)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedEquilibre(SymfonyStyle $io): void
    {
        $io->section('Équilibre de Vie Questions...');
        $domains = [
            ['sante', 'Santé & Vitalité', 'Sur une échelle de 1 à 10, comment évaluez-vous votre santé physique et votre niveau de vitalité au quotidien ?'],
            ['famille', 'Relations Familiales', 'Sur une échelle de 1 à 10, quel est votre niveau d’épanouissement dans vos relations familiales ?'],
            ['finances', 'Finances & Sécurité', 'Sur une échelle de 1 à 10, comment évaluez-vous votre niveau de sérénité et de sécurité financière ?'],
            ['carriere', 'Carrière & Projets', 'Sur une échelle de 1 à 10, quel est votre niveau d’accomplissement dans votre carrière et vos projets professionnels ?'],
            ['spiritualite', 'Spiritualité & Alignement', 'Sur une échelle de 1 à 10, comment évaluez-vous votre connexion à quelque chose de plus grand ou à votre philosophie de vie ?'],
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
            ['SPIRITUALITE', 'Spiritualité & Alignement', 'Votre connexion à un sens plus grand et votre alignement intérieur sont vos guides dominants.', 'Sérénité, intégrité profonde, résilience face aux crises, sagesse', 'Risque de fuite de la réalité matérielle ou de dogmatisme', 'Vivre chaque instant en harmonie avec sa vérité supérieure.', 'Pacificateur, cherche des partages d\'âme authentiques', 'Coaching de vie, philosophie, écriture, animation de cercles éthiques', 'Trouve son calme dans le silence, la nature et la contemplation', 'Ancrer ses convictions spirituelles dans des actions concrètes et quotidiennes.'],
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
