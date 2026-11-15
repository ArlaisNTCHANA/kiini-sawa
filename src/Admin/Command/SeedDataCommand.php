<?php

namespace App\Admin\Command;

use App\Compte\Entity\User;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeDeveloppement\Entity\PlanDeDeveloppementPersonnel;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\Abonnement\Entity\Subscription;
use App\Compte\Entity\UserProfile;
use App\Notification\Entity\NotificationPreference;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Vision;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Entity\Passion;
use App\PlanDeCroissance\Entity\Principle;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\GrowthGoalScore;
use App\Rituels\Entity\Habit;
use App\Personnalite\Entity\ProfileTest;
use App\PlanDeVie\Entity\InternalBalance;
use App\Admin\Entity\AppConfig;
use App\Abonnement\Enum\SubscriptionStatus;
use App\Abonnement\Enum\SubscriptionTier;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeCroissance\Enum\CharacterAction;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Initialise les données d\'alignement et de démo complètes pour un utilisateur.',
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse email de l\'utilisateur à peupler', 'admin@kiinisawa.com')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe si l\'utilisateur est à créer', 'password123')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->note(sprintf('Utilisateur %s non trouvé, création en cours...', $email));
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_ADMIN']);

            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Initialize the 5 plans pivots
            $planDeVie = new PlanDeVie();
            $planDeVie->setUser($user);

            $planDeCroissance = new PlanDeCroissance();
            $planDeCroissance->setUser($user);

            $planDeDeveloppementPersonnel = new PlanDeDeveloppementPersonnel();
            $planDeDeveloppementPersonnel->setUser($user);

            $planDHygiene = new PlanDHygiene();
            $planDHygiene->setUser($user);

            $planDeTransmission = new PlanDeTransmission();
            $planDeTransmission->setUser($user);

            $user->setPlanDeVie($planDeVie);
            $user->setPlanDeCroissance($planDeCroissance);
            $user->setPlanDeDeveloppementPersonnel($planDeDeveloppementPersonnel);
            $user->setPlanDHygiene($planDHygiene);
            $user->setPlanDeTransmission($planDeTransmission);

            // Initialize subscription
            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setTier(SubscriptionTier::FREE);
            $subscription->setStatus(SubscriptionStatus::ACTIVE);
            $user->setSubscription($subscription);

            // Initialize user profile
            $userProfile = new UserProfile();
            $userProfile->setUser($user);
            $user->setUserProfile($userProfile);

            // Initialize notification preference
            $notificationPreference = new NotificationPreference();
            $notificationPreference->setUser($user);
            $user->setNotificationPreference($notificationPreference);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $io->success(sprintf('Utilisateur %s créé avec succès.', $email));
        } else {
            $io->info(sprintf('Utilisateur %s trouvé. Nettoyage des anciennes données...', $email));
        }

        // Clean-up existing relations to avoid duplicate seed data
        $planDeVie = $user->getPlanDeVie();
        if ($planDeVie) {
            $purpose = $planDeVie->getPurpose();
            if ($purpose) {
                $this->entityManager->remove($purpose);
                $planDeVie->setPurpose(null);
            }
            $vision = $planDeVie->getVision();
            if ($vision) {
                $this->entityManager->remove($vision);
                $planDeVie->setVision(null);
            }
            foreach ($planDeVie->getValues() as $value) {
                $this->entityManager->remove($value);
            }
            $planDeVie->getValues()->clear();
            foreach ($planDeVie->getPassions() as $passion) {
                $this->entityManager->remove($passion);
            }
            $planDeVie->getPassions()->clear();
            foreach ($planDeVie->getLifeGoals() as $lifeGoal) {
                $this->entityManager->remove($lifeGoal);
            }
            $planDeVie->getLifeGoals()->clear();
        }

        $planDeCroissance = $user->getPlanDeCroissance();
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $growthGoal) {
                $this->entityManager->remove($growthGoal);
            }
            $planDeCroissance->getGrowthGoals()->clear();
        }

        $planDeTransmission = $user->getPlanDeTransmission();
        if ($planDeTransmission) {
            foreach ($planDeTransmission->getLegacyGoals() as $legacyGoal) {
                $this->entityManager->remove($legacyGoal);
            }
            $planDeTransmission->getLegacyGoals()->clear();
        } else {
            $planDeTransmission = new PlanDeTransmission();
            $planDeTransmission->setUser($user);
            $user->setPlanDeTransmission($planDeTransmission);
            $this->entityManager->persist($planDeTransmission);
        }

        $planDHygiene = $user->getPlanDHygiene();
        if ($planDHygiene) {
            foreach ($planDHygiene->getHygieneDomains() as $hygieneDomain) {
                $this->entityManager->remove($hygieneDomain);
            }
            $planDHygiene->getHygieneDomains()->clear();
        } else {
            $planDHygiene = new PlanDHygiene();
            $planDHygiene->setUser($user);
            $user->setPlanDHygiene($planDHygiene);
            $this->entityManager->persist($planDHygiene);
        }

        foreach ($user->getPrinciples() as $principle) {
            $this->entityManager->remove($principle);
        }
        $user->getPrinciples()->clear();

        foreach ($user->getProfileTests() as $profileTest) {
            $this->entityManager->remove($profileTest);
        }
        $user->getProfileTests()->clear();

        foreach ($user->getInternalBalances() as $internalBalance) {
            $this->entityManager->remove($internalBalance);
        }
        $user->getInternalBalances()->clear();

        foreach ($user->getHabits() as $habit) {
            $this->entityManager->remove($habit);
        }
        $user->getHabits()->clear();

        $this->entityManager->flush();
        $io->note('Nettoyage terminé. Début de l\'alimentation...');

        // 1. Update User Profile Info
        $userProfile = $user->getUserProfile();
        if (!$userProfile) {
            $userProfile = new UserProfile();
            $userProfile->setUser($user);
            $user->setUserProfile($userProfile);
        }
        $userProfile->setFirstName('Nyamsi');
        $userProfile->setLastName('Ntchana');
        $userProfile->setBio('Passionné par le développement humain, la stratégie et l\'impact social.');
        $userProfile->setCountry('Cameroun');
        $userProfile->setCity('Douala');
        $userProfile->setProfession('Entrepreneur & Coach');
        $userProfile->setOnboardingCompleted(true);
        $this->entityManager->persist($userProfile);

        // 2. Purpose & Vision
        $purpose = new Purpose();
        $purpose->setPlanDeVie($planDeVie);
        $purpose->setStatement('Aider les entrepreneurs africains à construire des systèmes durables de croissance personnelle et d\'impact collectif.');
        $purpose->setLongStatement('Je crois profondément que chaque personne porte en elle un potentiel inexploité. Ma mission est de créer les outils, les espaces et les relations qui permettent aux leaders africains de passer de l\'intention à l\'exécution.');
        $purpose->setHypotheses('Si je développe mes compétences en coaching et en technologie, je pourrai toucher 10 000 personnes en 5 ans.');
        $this->entityManager->persist($purpose);
        $planDeVie->setPurpose($purpose);

        $vision = new Vision();
        $vision->setPlanDeVie($planDeVie);
        $vision->setContent('Devenir une référence africaine dans le domaine du développement personnel stratégique. D\'ici 2030, avoir co-créé une communauté de 10 000 leaders intentionnels qui transforment leur continent.');
        $vision->setHorizon('2030');
        $vision->setBeneficiaries('Entrepreneurs, managers et coaches africains âgés de 25 à 45 ans.');
        $vision->setIndicators('Nombre de clients transformés, revenus générés, communautés créées, livres écrits.');
        $this->entityManager->persist($vision);
        $planDeVie->setVision($vision);

        // 3. Values
        $valuesData = [
            ['name' => 'Excellence', 'behavior' => 'Je ne soumets que mon meilleur travail, même quand personne ne regarde.', 'category' => 'real'],
            ['name' => 'Intégrité', 'behavior' => 'Mes actes sont alignés avec mes paroles, toujours.', 'category' => 'real'],
            ['name' => 'Impact', 'behavior' => 'Chaque projet que j\'accepte doit laisser une trace positive et mesurable.', 'category' => 'declared'],
            ['name' => 'Discipline', 'behavior' => 'Je respecte mes engagements même quand la motivation manque.', 'category' => 'declared'],
            ['name' => 'Validation Externe', 'behavior' => 'Dépendance excessive à l\'approbation d\'autrui pour me sentir compétent.', 'category' => 'counter'],
        ];
        foreach ($valuesData as $v) {
            $value = new Value();
            $value->setPlanDeVie($planDeVie);
            $value->setName($v['name']);
            $value->setBehavior($v['behavior']);
            $value->setCategory($v['category']);
            $this->entityManager->persist($value);
            $planDeVie->addValue($value);
        }

        // 4. Passions
        $passionsData = [
            ['name' => 'Coaching & Mentorat', 'context' => 'Accompagner des entrepreneurs dans leur croissance'],
            ['name' => 'Lecture & Apprentissage', 'context' => '1 livre par semaine, focus leadership & psychologie'],
            ['name' => 'Technologie & Innovation', 'context' => 'Créer des outils qui démocratisent le développement personnel'],
            ['name' => 'Écriture', 'context' => 'Blog, journaux stratégiques et futur livre'],
        ];
        foreach ($passionsData as $p) {
            $passion = new Passion();
            $passion->setPlanDeVie($planDeVie);
            $passion->setName($p['name']);
            $passion->setContext($p['context']);
            $this->entityManager->persist($passion);
            $planDeVie->addPassion($passion);
        }

        // 5. Principles
        $principlesData = [
            ['pillar' => 'CROISSANCE', 'title' => 'Systèmes > Objectifs', 'description' => 'Je ne me concentre pas sur les résultats mais sur les systèmes qui les produisent.'],
            ['pillar' => 'CROISSANCE', 'title' => 'Caractère avant compétence', 'description' => 'Ce que je deviens est plus important que ce que je sais faire.'],
            ['pillar' => 'CROISSANCE', 'title' => 'Une chose à la fois', 'description' => 'Je fais peu de choses, mais je les fais profondément.'],
            ['pillar' => 'DEVELOPPEMENT', 'title' => 'Faire, puis perfectionner', 'description' => 'Le mouvement vaut mieux que la paralysie par l\'analyse.'],
            ['pillar' => 'DEVELOPPEMENT', 'title' => 'Apprendre en public', 'description' => 'Je partage ce que j\'apprends, même imparfaitement, pour consolider ma maîtrise.'],
            ['pillar' => 'DEVELOPPEMENT', 'title' => '1% mieux chaque jour', 'description' => 'L\'amélioration constante et marginale produit des résultats exponentiels.'],
            ['pillar' => 'CONTRIBUTION', 'title' => 'Réseau avant besoin', 'description' => 'Je construis mes relations avant d\'en avoir besoin.'],
            ['pillar' => 'CONTRIBUTION', 'title' => 'Donner sans attendre de retour', 'description' => 'La valeur crée est la monnaie de l\'impact durable.'],
            ['pillar' => 'CONTRIBUTION', 'title' => 'Laisser chaque espace meilleur', 'description' => 'Chaque interaction doit enrichir la personne en face.'],
            ['pillar' => 'DISCIPLINE', 'title' => 'La liberté naît de la discipline', 'description' => 'Plus mes routines sont solides, plus mon énergie est libre pour l\'essentiel.'],
            ['pillar' => 'DISCIPLINE', 'title' => 'Rituel > Motivation', 'description' => 'Je ne compte pas sur la motivation, je crée des rituels qui m\'y conduisent.'],
            ['pillar' => 'DISCIPLINE', 'title' => 'Environnement > Volonté', 'description' => 'Je conçois mon environnement pour que le bon comportement soit le plus facile.'],
        ];
        foreach ($principlesData as $pr) {
            $principle = new Principle();
            $principle->setUser($user);
            $principle->setPillar($pr['pillar']);
            $principle->setTitle($pr['title']);
            $principle->setDescription($pr['description']);
            $this->entityManager->persist($principle);
            $user->addPrinciple($principle);
        }

        // 6. Life Goals
        $lifeGoalsData = [
            ['title' => 'Devenir un coach certifié ICF', 'category' => 'DO', 'priority' => 'HIGH', 'deadline' => '2025-12-31', 'progress' => 40],
            ['title' => 'Lancer ma plateforme de coaching en ligne', 'category' => 'DO', 'priority' => 'HIGH', 'deadline' => '2026-06-30', 'progress' => 25],
            ['title' => 'Écrire et publier mon premier livre', 'category' => 'GIVE', 'priority' => 'MEDIUM', 'deadline' => '2026-12-31', 'progress' => 10],
            ['title' => 'Acquérir mon appartement à Douala', 'category' => 'HAVE', 'priority' => 'HIGH', 'deadline' => '2027-01-01', 'progress' => 15],
            ['title' => 'Atteindre l\'équilibre intérieur et la paix profonde', 'category' => 'BE', 'priority' => 'HIGH', 'deadline' => '2025-12-31', 'progress' => 60],
            ['title' => 'Mentorer 100 entrepreneurs africains', 'category' => 'GIVE', 'priority' => 'MEDIUM', 'deadline' => '2028-01-01', 'progress' => 5],
            ['title' => 'Générer 5M FCFA/mois de revenus récurrents', 'category' => 'HAVE', 'priority' => 'HIGH', 'deadline' => '2027-06-30', 'progress' => 20],
        ];
        $lifeGoalsMap = [];
        foreach ($lifeGoalsData as $lg) {
            $lifeGoal = new LifeGoal();
            $lifeGoal->setPlanDeVie($planDeVie);
            $lifeGoal->setTitle($lg['title']);
            $lifeGoal->setCategory($lg['category']);
            $lifeGoal->setPriority($lg['priority']);
            if ($lg['deadline']) {
                $lifeGoal->setDeadline(new \DateTime($lg['deadline']));
            }
            $lifeGoal->setProgress($lg['progress']);
            $this->entityManager->persist($lifeGoal);
            $planDeVie->addLifeGoal($lifeGoal);

            $lifeGoalsMap[$lg['title']] = $lifeGoal;
        }

        // 7. Growth Goals, Habits & Scores
        $endDate = new \DateTime();
        $endDate->modify('+3 months');

        $growthGoalsConfig = [
            [
                'title' => 'Développer ma discipline émotionnelle',
                'axis' => GrowthAxis::CHARACTER,
                'subtype' => 'build',
                'characterAction' => CharacterAction::DEVELOP,
                'strategy' => 'Pratique quotidienne de la méditation et tenue d\'un journal de réflexion.',
                'metric' => 'Séances de méditation / semaine',
                'progress' => 55,
                'deadline' => '2025-12-31',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
                'habits' => [
                    ['title' => 'Méditation 15min (matin)', 'frequency' => HabitFrequency::DAILY],
                    ['title' => 'Journal de gratitude', 'frequency' => HabitFrequency::DAILY],
                    ['title' => 'Revue hebdomadaire intentionnelle', 'frequency' => HabitFrequency::WEEKLY],
                ],
            ],
            [
                'title' => 'Renforcer ma conviction sur mon Purpose',
                'axis' => GrowthAxis::CHARACTER,
                'subtype' => 'consolidate',
                'characterAction' => CharacterAction::STRENGTHEN,
                'strategy' => 'Lire 2 biographies de leaders africains par trimestre, revoir mon plan de vie mensuellement.',
                'metric' => 'Niveau de certitude (autoévaluation /10)',
                'progress' => 70,
                'deadline' => null,
                'supportedLifeGoals' => ['Mentorer 100 entrepreneurs africains'],
                'habits' => [
                    ['title' => 'Lecture 30min (soir)', 'frequency' => HabitFrequency::DAILY],
                    ['title' => 'Relire mon Purpose mensuel', 'frequency' => HabitFrequency::MONTHLY],
                ],
            ],
            [
                'title' => 'Supprimer le besoin de validation externe',
                'axis' => GrowthAxis::CHARACTER,
                'subtype' => 'build',
                'characterAction' => CharacterAction::ABANDON,
                'strategy' => 'Prendre des décisions sans demander systématiquement l\'avis des autres. Tenir un journal des décisions autonomes.',
                'metric' => 'Décisions prises de manière autonome / semaine',
                'progress' => 30,
                'deadline' => null,
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
                'habits' => [
                    ['title' => 'Prendre 1 décision autonome par jour', 'frequency' => HabitFrequency::DAILY],
                ],
            ],
            [
                'title' => 'Relation avec mon mentor stratégique (Dr. Kamga)',
                'axis' => GrowthAxis::RELATION,
                'type' => 'orientation',
                'subtype' => 'leverage',
                'characterAction' => null,
                'strategy' => 'Maintenir le contact mensuel, apporter de la valeur avant de demander.',
                'metric' => 'Rencontres par trimestre',
                'progress' => 80,
                'deadline' => null,
                'supportedLifeGoals' => ['Devenir un coach certifié ICF'],
                'habits' => [
                    ['title' => 'Envoyer une note de valeur au mentor', 'frequency' => HabitFrequency::MONTHLY],
                ],
            ],
            [
                'title' => 'Réseau de soutien émotionnel (Famille & Amis proches)',
                'axis' => GrowthAxis::RELATION,
                'type' => 'soutien',
                'subtype' => 'consolidate',
                'characterAction' => null,
                'strategy' => 'Appels hebdomadaires, sorties mensuelles, présence intentionnelle.',
                'metric' => 'Connexions émotionnelles fortes maintenues',
                'progress' => 75,
                'deadline' => null,
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
                'habits' => [
                    ['title' => 'Appel en famille (dimanche)', 'frequency' => HabitFrequency::WEEKLY],
                ],
            ],
            [
                'title' => 'Groupe de responsabilité (Mastermind)',
                'axis' => GrowthAxis::RELATION,
                'type' => 'responsabilite',
                'subtype' => 'build',
                'characterAction' => null,
                'strategy' => 'Organiser les sessions, partager des objectifs mesurables, suivi des engagements.',
                'metric' => 'Sessions tenues / mois',
                'progress' => 50,
                'deadline' => null,
                'supportedLifeGoals' => ['Lancer ma plateforme de coaching en ligne'],
                'habits' => [
                    ['title' => 'Préparer rapport hebdo pour mastermind', 'frequency' => HabitFrequency::WEEKLY],
                ],
            ],
            [
                'title' => 'Réseau de croissance professionnelle (LinkedIn)',
                'axis' => GrowthAxis::RELATION,
                'type' => 'croissance',
                'subtype' => 'build',
                'characterAction' => null,
                'strategy' => 'Publier du contenu, commenter des posts stratégiques, envoyer 5 DMs/semaine.',
                'metric' => 'Nouveaux contacts qualifiés / mois',
                'progress' => 30,
                'deadline' => null,
                'supportedLifeGoals' => ['Lancer ma plateforme de coaching en ligne', 'Générer 5M FCFA/mois de revenus récurrents'],
                'habits' => [
                    ['title' => 'Engager 3 connexions LinkedIn', 'frequency' => HabitFrequency::WEEKLY],
                ],
            ],
            [
                'title' => 'Optimiser mon espace de travail (bureau deep work)',
                'axis' => GrowthAxis::RELATION,
                'type' => 'environnement',
                'subtype' => 'leverage',
                'characterAction' => null,
                'strategy' => 'Réorganisation de l\'espace, éliminer les distractions numériques, ritualiser l\'entrée au travail.',
                'metric' => 'Heures de focus par jour',
                'progress' => 65,
                'deadline' => null,
                'supportedLifeGoals' => ['Lancer ma plateforme de coaching en ligne'],
                'habits' => [
                    ['title' => 'Ranger le bureau avant de partir', 'frequency' => HabitFrequency::DAILY],
                    ['title' => 'Routine shutdown du soir', 'frequency' => HabitFrequency::DAILY],
                    ['title' => '90min de travail profond (matin)', 'frequency' => HabitFrequency::DAILY],
                ],
            ],
            [
                'title' => 'Communication & présentations publiques',
                'axis' => GrowthAxis::COMPETENCE,
                'type' => 'COMPETENCE',
                'subtype' => 'leverage',
                'characterAction' => null,
                'strategy' => 'Pratiquer le pitch lors de réunions publiques, rejoindre Toastmasters.',
                'metric' => 'Présentations réussies',
                'progress' => 80,
                'deadline' => null,
                'supportedLifeGoals' => ['Devenir un coach certifié ICF', 'Écrire et publier mon premier livre'],
                'habits' => [],
            ],
            [
                'title' => 'Marketing digital et acquisition clients',
                'axis' => GrowthAxis::COMPETENCE,
                'type' => 'COMPETENCE',
                'subtype' => 'build',
                'characterAction' => null,
                'strategy' => 'Suivre une formation en ligne sur Google Ads et faire des tests sur un petit budget.',
                'metric' => 'Taux d\'acquisition',
                'progress' => 30,
                'deadline' => null,
                'supportedLifeGoals' => ['Lancer ma plateforme de coaching en ligne', 'Générer 5M FCFA/mois de revenus récurrents'],
                'habits' => [],
            ],
            [
                'title' => 'Maîtrise des concepts d\'analyse financière',
                'axis' => GrowthAxis::COMPETENCE,
                'type' => 'CONNAISSANCE',
                'subtype' => 'consolidate',
                'characterAction' => null,
                'strategy' => 'Lire 1 livre de finance d\'entreprise par mois et suivre un cours en ligne.',
                'metric' => 'Heures d\'étude par semaine',
                'progress' => 50,
                'deadline' => null,
                'supportedLifeGoals' => ['Générer 5M FCFA/mois de revenus récurrents'],
                'habits' => [],
            ],
            [
                'title' => 'Programmation bas niveau (C++)',
                'axis' => GrowthAxis::COMPETENCE,
                'type' => 'CONNAISSANCE',
                'subtype' => 'absence',
                'characterAction' => null,
                'strategy' => 'Déléguer tous les sujets systèmes/C++ à des experts.',
                'metric' => 'Zéro ligne de code bas niveau écrite',
                'progress' => 100,
                'deadline' => null,
                'supportedLifeGoals' => ['Lancer ma plateforme de coaching en ligne'],
                'habits' => [],
            ],
        ];

        foreach ($growthGoalsConfig as $gg) {
            $growthGoal = new GrowthGoal();
            $growthGoal->setPlanDeCroissance($planDeCroissance);
            $growthGoal->setTitle($gg['title']);
            $growthGoal->setAxis($gg['axis']);
            $growthGoal->setCharacterAction($gg['characterAction']);
            $growthGoal->setStrategy($gg['strategy']);
            $growthGoal->setMetric($gg['metric']);
            $growthGoal->setProgress($gg['progress']);
            if ($gg['deadline']) {
                $growthGoal->setDeadline(new \DateTime($gg['deadline']));
            }
            if (isset($gg['type'])) {
                $growthGoal->setType($gg['type']);
            }
            if (isset($gg['subtype'])) {
                $growthGoal->setSubtype($gg['subtype']);
            }
            if (isset($gg['supportedLifeGoals'])) {
                foreach ($gg['supportedLifeGoals'] as $lgTitle) {
                    if (isset($lifeGoalsMap[$lgTitle])) {
                        $growthGoal->addSupportedLifeGoal($lifeGoalsMap[$lgTitle]);
                    }
                }
            }
            $this->entityManager->persist($growthGoal);
            $planDeCroissance->addGrowthGoal($growthGoal);

            // Habits
            foreach ($gg['habits'] as $h) {
                $habit = new Habit();
                $habit->setUser($user);
                $habit->setGrowthGoal($growthGoal);
                $habit->setTitle($h['title']);
                $habit->setFrequency($h['frequency']);
                $habit->setEndDate($endDate);
                $this->entityManager->persist($habit);
                $user->addHabit($habit);
            }

            // Score
            $score = new GrowthGoalScore();
            $score->setGrowthGoal($growthGoal);
            $score->setScore(rand(6, 8));
            $score->setProgressValue($gg['progress']);
            $score->setNote('Progression régulière, maintenir le cap.');
            $score->setDate(new \DateTime());
            $this->entityManager->persist($score);
        }

        // 8. Personality Tests
        $testsData = [
            [
                'type' => 'MBTI',
                'result' => json_encode(['type' => 'ENFJ']),
                'date' => '2025-01-15',
                'insights' => 'Profil "Le Protagoniste" - Leadership naturel, empathie forte, vision claire.',
            ],
            [
                'type' => 'DISC',
                'result' => json_encode(['D' => 78, 'I' => 85, 'S' => 42, 'C' => 63]),
                'date' => '2025-02-01',
                'insights' => 'Profil Influent/Dominant. Excellent communicant, orienté résultats.',
            ],
            [
                'type' => 'ENNEAGRAMME',
                'result' => json_encode(['type' => '3', 'wing' => '2']),
                'date' => '2025-01-20',
                'insights' => 'Type 3 "L\'Ambitieux" avec aile 2. Performant, orienté succès.',
            ],
            [
                'type' => 'HYGIENE',
                'result' => json_encode([
                    'axes' => [
                        'physique' => 6.5,
                        'mental' => 4.5,
                        'relationnel' => 8.0,
                        'spirituel' => 7.5,
                        'ressources' => 5.5,
                        'environnement' => 4.0,
                    ],
                    'details' => [
                        'physique' => [
                            'Sommeil & Énergie' => 6.5,
                            'Nutrition & Digestion' => 7.0,
                            'Hydratation' => 6.0,
                            'Activité physique' => 5.0,
                        ],
                        'mental' => [
                            'Repos cognitif' => 5.0,
                            'Gestion du stress' => 4.0,
                            'Calme & Loisirs' => 5.0,
                            'Clarté mentale' => 4.0,
                        ],
                        'relationnel' => [
                            'Écoute active' => 8.0,
                            'Partage & Moments' => 8.0,
                            'Expression des besoins' => 8.0,
                            'Soutien & Réseau' => 8.0,
                        ],
                        'spirituel' => [
                            'Sens & Utilité' => 7.0,
                            'Alignement' => 8.0,
                            'Paix intérieure' => 7.0,
                            'Pratique personnelle' => 8.0,
                        ],
                        'ressources' => [
                            'Gestion du temps' => 6.0,
                            'Projets personnels' => 5.0,
                            'Limites d’énergie' => 6.0,
                            'Récupération' => 5.0,
                        ],
                        'environnement' => [
                            'Clarté de l’espace' => 4.0,
                            'Lumière naturelle' => 4.0,
                            'Niveau sonore' => 4.0,
                            'Contact avec la nature' => 4.0,
                        ],
                    ],
                ]),
                'date' => '2025-02-10',
                'insights' => 'Bonne hygiène relationnelle et spirituelle. Améliorer : mental, environnement et ressources.',
            ],
            [
                'type' => 'BIG5',
                'result' => json_encode(['O' => 88, 'C' => 72, 'E' => 80, 'A' => 75, 'N' => 35]),
                'date' => '2025-01-25',
                'insights' => 'Très ouvert à l\'expérience, extraverti et agréable. Faible névrosisme.',
            ],
        ];
        foreach ($testsData as $t) {
            $profileTest = new ProfileTest();
            $profileTest->setUser($user);
            $profileTest->setType($t['type']);
            $profileTest->setResult($t['result']);
            $profileTest->setInsights($t['insights']);
            $profileTest->setDate(new \DateTime($t['date']));
            $this->entityManager->persist($profileTest);
            $user->addProfileTest($profileTest);
        }

        // 9. Internal Balance
        $balanceData = [
            ['type' => 'COMPETENCE', 'subtype' => 'LEVERAGE', 'text' => 'Communication & présentations publiques'],
            ['type' => 'COMPETENCE', 'subtype' => 'LEVERAGE', 'text' => 'Gestion de projets entrepreneuriaux'],
            ['type' => 'COMPETENCE', 'subtype' => 'BUILD', 'text' => 'Maîtrise des outils d\'analyse financière'],
            ['type' => 'COMPETENCE', 'subtype' => 'BUILD', 'text' => 'Marketing digital et acquisition clients'],
            ['type' => 'COMPETENCE', 'subtype' => 'CONSOLIDATE', 'text' => 'Coaching et accompagnement individuel'],
            ['type' => 'CHARACTER', 'subtype' => 'STRENGTHEN', 'text' => 'Discipline dans les périodes de faible motivation'],
            ['type' => 'CHARACTER', 'subtype' => 'DEVELOP', 'text' => 'Patience face à la lenteur des résultats'],
            ['type' => 'CHARACTER', 'subtype' => 'ABANDON', 'text' => 'Besoin de validation externe'],
            
            // Convictions
            ['type' => 'CONVICTION', 'subtype' => 'deep', 'text' => 'Chaque être humain possède un potentiel unique qui attend d\'être libéré.'],
            ['type' => 'CONVICTION', 'subtype' => 'deep', 'text' => 'L\'intégrité personnelle est la base de toute influence durable.'],
            ['type' => 'CONVICTION', 'subtype' => 'medium', 'text' => 'La discipline quotidienne produit des résultats exponentiels sur le long terme.'],
            ['type' => 'CONVICTION', 'subtype' => 'medium', 'text' => 'L\'échec est simplement un retour d\'information nécessaire à l\'apprentissage.'],
            ['type' => 'CONVICTION', 'subtype' => 'light', 'text' => 'Le travail à distance asynchrone est plus productif que le travail synchrone.'],
            
            // Croyances Limitantes
            ['type' => 'LIMITING_BELIEF', 'subtype' => null, 'text' => 'Je dois tout faire moi-même pour que ce soit parfaitement exécuté.'],
            ['type' => 'LIMITING_BELIEF', 'subtype' => null, 'text' => 'Je ne suis pas encore assez légitime pour guider des leaders chevronnés.'],
        ];
        foreach ($balanceData as $b) {
            $internalBalance = new InternalBalance();
            $internalBalance->setUser($user);
            $internalBalance->setType($b['type']);
            $internalBalance->setSubtype($b['subtype']);
            $internalBalance->setText($b['text']);
            $this->entityManager->persist($internalBalance);
            $user->addInternalBalance($internalBalance);
        }

        // 10. Legacy Goals (Transmission & Héritage)
        $legacyData = [
            [
                'title' => 'Créer une fondation d\'aide à la scolarisation des filles',
                'subtype' => 'build',
                'targetBeneficiary' => 'Jeunes filles en milieu rural',
                'supportedLifeGoals' => ['Mentorer 100 entrepreneurs africains'],
            ],
            [
                'title' => 'Écrire un recueil de leçons de leadership pour la prochaine génération',
                'subtype' => 'leverage',
                'targetBeneficiary' => 'Jeunes professionnels',
                'supportedLifeGoals' => ['Écrire et publier mon premier livre'],
            ]
        ];
        foreach ($legacyData as $ld) {
            $legacyGoal = new \App\PlanDeTransmission\Entity\LegacyGoal();
            $legacyGoal->setPlanDeTransmission($planDeTransmission);
            $legacyGoal->setTitle($ld['title']);
            $legacyGoal->setSubtype($ld['subtype']);
            $legacyGoal->setTargetBeneficiary($ld['targetBeneficiary']);
            if (isset($ld['supportedLifeGoals'])) {
                foreach ($ld['supportedLifeGoals'] as $lgTitle) {
                    if (isset($lifeGoalsMap[$lgTitle])) {
                        $legacyGoal->addSupportedLifeGoal($lifeGoalsMap[$lgTitle]);
                    }
                }
            }
            $this->entityManager->persist($legacyGoal);
            $planDeTransmission->addLegacyGoal($legacyGoal);
        }

        // 11. Hygiene Domains (Hygiène de Vie)
        // Auto-populated using HYGIENE profile test:
        // sommeil: 6.5 -> consolidate
        // relations: 8 -> leverage
        // stress: 4 -> consolidate
        // spiritualite: 7 -> leverage
        // repos/resources: 5 -> consolidate
        $hygieneData = [
            [
                'name' => 'Sommeil & Énergie',
                'subtype' => 'consolidate',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
            ],
            [
                'name' => 'Gestion du stress',
                'subtype' => 'consolidate',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
            ],
            [
                'name' => 'Partage & Moments',
                'subtype' => 'leverage',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
            ],
            [
                'name' => 'Paix intérieure',
                'subtype' => 'leverage',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
            ],
            [
                'name' => 'Gestion du temps',
                'subtype' => 'consolidate',
                'supportedLifeGoals' => ['Générer 5M FCFA/mois de revenus récurrents'],
            ],
            [
                'name' => 'Clarté de l’espace',
                'subtype' => 'consolidate',
                'supportedLifeGoals' => ['Atteindre l\'équilibre intérieur et la paix profonde'],
            ]
        ];
        foreach ($hygieneData as $hd) {
            $hygieneDomain = new \App\PlanDHygiene\Entity\HygieneDomain();
            $hygieneDomain->setPlanDHygiene($planDHygiene);
            $hygieneDomain->setName($hd['name']);
            $hygieneDomain->setSubtype($hd['subtype']);
            if (isset($hd['supportedLifeGoals'])) {
                foreach ($hd['supportedLifeGoals'] as $lgTitle) {
                    if (isset($lifeGoalsMap[$lgTitle])) {
                        $hygieneDomain->addSupportedLifeGoal($lifeGoalsMap[$lgTitle]);
                    }
                }
            }
            $this->entityManager->persist($hygieneDomain);
            $planDHygiene->addHygieneDomain($hygieneDomain);
        }

        // 10. AppConfig (default config)
        $appConfigRepository = $this->entityManager->getRepository(AppConfig::class);
        $appConfig = $appConfigRepository->find('default');
        if (!$appConfig) {
            $appConfig = new AppConfig();
            $appConfig->setId('default');
            $appConfig->setProMonthlyPrice(9.99);
            $appConfig->setProYearlyPrice(79.99);
            $appConfig->setTrialDurationDays(14);
            $this->entityManager->persist($appConfig);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Seeding réussi de toutes les données d\'alignement pour l\'utilisateur %s !', $email));

        return Command::SUCCESS;
    }
}
