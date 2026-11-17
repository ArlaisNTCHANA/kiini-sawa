<?php

namespace App\Tests\PlanDeVie\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserPlanControllerTest extends WebTestCase
{
    public function testLifePlanRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plan-de-vie');

        $this->assertResponseRedirects('/login');
    }

    public function testLifePlanRendersSuccessfullyWithCorrectVocabulary(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $client->request('GET', '/plan-de-vie');

        $this->assertResponseIsSuccessful();
        
        // Assert harmonized vocabulary is present in templates/plan_de_vie/index.html.twig
        $this->assertSelectorTextContains('body', 'But de Dieu');
        $this->assertSelectorTextContains('body', 'Objectifs spécifiques');
        $this->assertSelectorTextContains('body', 'Compétence & Connaissance');
        $this->assertSelectorTextContains('body', 'Compétences');
        $this->assertSelectorTextContains('body', 'Connaissances');
        $this->assertSelectorTextContains('body', 'À mettre à profit');
        $this->assertSelectorTextContains('body', 'À construire');
        $this->assertSelectorTextContains('body', 'À consolider');
        $this->assertSelectorTextContains('body', 'Absence assumée');
        $this->assertSelectorTextContains('body', 'Caractère & Conviction');
        $this->assertSelectorTextContains('body', 'Relation & Réseau');
        $this->assertSelectorTextContains('body', 'Héritage & Transmission');
        $this->assertSelectorTextContains('body', 'Ressources & Moyens');
        $this->assertSelectorTextContains('body', 'Énergie & Transmission');
        
        // Assert new dashboard tabs are present
        $this->assertSelectorTextContains('body', 'Échéancier');
        $this->assertSelectorTextContains('body', 'carte');
        $this->assertSelectorTextContains('body', 'Analyse');
    }

    public function testSingleStepEditorLayout(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // Request Step 1 in single edit mode
        $crawler = $client->request('GET', '/plan-de-vie/assistant?step=1&single=1');

        $this->assertResponseIsSuccessful();

        // Single editor should render within default page layout (no .wizard-body backdrop)
        $this->assertSelectorNotExists('.wizard-body');
        
        // Single editor should have header with Step 1 title
        $this->assertSelectorTextContains('h1', 'Modifier mon But de Dieu');

        // Check buttons
        $this->assertSelectorTextContains('body', 'Annuler');
        $this->assertSelectorTextContains('button[name="save_single"]', 'Enregistrer les modifications');
    }

    public function testMultiStepWizardLayout(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // Request wizard mode (single=0)
        $client->request('GET', '/plan-de-vie/assistant?step=1&single=0');

        $this->assertResponseIsSuccessful();

        // Multi-step wizard should render with the immersive backdrop class
        $this->assertSelectorExists('.wizard-body');

        // Buttons
        $this->assertSelectorTextContains('body', 'Continuer');
    }

    public function testLifePlanShowsDefaultPurposeStatementWhenEmpty(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDeVie = $testUser->getPlanDeVie();
        $originalStatement = null;
        if ($planDeVie && $planDeVie->getPurpose()) {
            $originalStatement = $planDeVie->getPurpose()->getStatement();
            $planDeVie->getPurpose()->setStatement('');
            $em->flush();
        }

        try {
            $client->loginUser($testUser);
            $client->request('GET', '/plan-de-vie');

            $this->assertResponseIsSuccessful();
            
            $config = $em->getRepository(\App\Admin\Entity\AppConfig::class)->find('default');
            $defaultText = $config->getDefaultPurposeStatement();
            
            $this->assertSelectorTextContains('body', $defaultText);
        } finally {
            if ($planDeVie && $planDeVie->getPurpose() && $originalStatement !== null) {
                $planDeVie->getPurpose()->setStatement($originalStatement);
                $em->flush();
            }
        }
    }

    public function testSaveLifeGoalsWithDeadlines(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDeVie = $testUser->getPlanDeVie();

        // Backup existing goals to restore them later
        $backupGoals = [];
        if ($planDeVie) {
            foreach ($planDeVie->getLifeGoals() as $lg) {
                $backupGoals[] = [
                    'title' => $lg->getTitle(),
                    'category' => $lg->getCategory(),
                    'priority' => $lg->getPriority(),
                    'metric' => $lg->getMetric(),
                    'progress' => $lg->getProgress(),
                    'deadline' => $lg->getDeadline(),
                ];
            }
        }
        
        try {
            $client->loginUser($testUser);
            
            // Post goals with deadlines
            $client->request('POST', '/plan-de-vie/assistant?step=7&single=1', [
                'life_goals_be' => ['Devenir un leader inspirant', 'Être calme'],
                'life_goals_be_deadlines' => ['2026-12-31', ''],
                'life_goals_do' => ['Lancer un produit'],
                'life_goals_do_deadlines' => ['2027-06-30'],
                'life_goals_have' => [],
                'life_goals_have_deadlines' => [],
                'life_goals_give' => [],
                'life_goals_give_deadlines' => [],
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Clear entity manager to force reload from DB
            $em->clear();

            // Verify in database
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeVie = $testUser->getPlanDeVie();
            $this->assertNotNull($planDeVie);

            $goals = $planDeVie->getLifeGoals();
            
            $beGoals = [];
            $doGoals = [];
            foreach ($goals as $g) {
                if ($g->getCategory() === 'BE') {
                    $beGoals[] = $g;
                } elseif ($g->getCategory() === 'DO') {
                    $doGoals[] = $g;
                }
            }

            $this->assertCount(2, $beGoals);
            $this->assertCount(1, $doGoals);

            // Sort by id or title to ensure deterministic checks
            usort($beGoals, fn($a, $b) => strcmp($a->getTitle(), $b->getTitle()));

            // 'Devenir un leader inspirant' comes first alphabetically
            $this->assertEquals('Devenir un leader inspirant', $beGoals[0]->getTitle());
            $this->assertNotNull($beGoals[0]->getDeadline());
            $this->assertEquals('2026-12-31', $beGoals[0]->getDeadline()->format('Y-m-d'));

            $this->assertEquals('Être calme', $beGoals[1]->getTitle());
            $this->assertNull($beGoals[1]->getDeadline());

            $this->assertEquals('Lancer un produit', $doGoals[0]->getTitle());
            $this->assertNotNull($doGoals[0]->getDeadline());
            $this->assertEquals('2027-06-30', $doGoals[0]->getDeadline()->format('Y-m-d'));

        } finally {
            // Restore backup goals
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeVie = $testUser->getPlanDeVie();
            if ($planDeVie) {
                foreach ($planDeVie->getLifeGoals() as $lg) {
                    $em->remove($lg);
                }
                $em->flush();

                foreach ($backupGoals as $bg) {
                    $lg = new \App\PlanDeVie\Entity\LifeGoal();
                    $lg->setPlanDeVie($planDeVie);
                    $lg->setTitle($bg['title']);
                    $lg->setCategory($bg['category']);
                    $lg->setPriority($bg['priority'] ?: 'MEDIUM');
                    $lg->setMetric($bg['metric']);
                    $lg->setProgress($bg['progress'] ?: 0);
                    $lg->setDeadline($bg['deadline']);
                    $em->persist($lg);
                }
                $em->flush();
            }
        }
    }

    public function testSaveGrowthGoalsStep8(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDeCroissance = $testUser->getPlanDeCroissance();

        // Backup existing COMPETENCE growth goals to restore them later
        $backupGoals = [];
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                    $backupGoals[] = [
                        'title' => $gg->getTitle(),
                        'strategy' => $gg->getStrategy(),
                        'metric' => $gg->getMetric(),
                        'progress' => $gg->getProgress(),
                        'deadline' => $gg->getDeadline(),
                        'type' => $gg->getType(),
                        'subtype' => $gg->getSubtype(),
                    ];
                }
            }
        }
        
        try {
            $client->loginUser($testUser);

            $lifeGoals = $testUser->getPlanDeVie()->getLifeGoals();
            $lgId1 = $lifeGoals[0]->getId();
            $lgId2 = $lifeGoals[1]->getId();
            
            // Post Step 8 data
            $client->request('POST', '/plan-de-vie/assistant?step=8&single=1', [
                'growth_competence_title' => ['Négocier des contrats', 'Programmer en PHP'],
                'growth_competence_strategy' => ['Pratique réelle', 'Suivre des cours Symfony'],
                'growth_competence_status' => ['leverage', 'consolidate'],
                'growth_competence_goals' => [
                    0 => [$lgId1],
                    1 => [$lgId2]
                ],
                'growth_connaissance_title' => ['Architecture des ordinateurs'],
                'growth_connaissance_strategy' => ['Lire des articles de blog'],
                'growth_connaissance_status' => ['build'],
                'growth_connaissance_goals' => [
                    0 => [$lgId1, $lgId2]
                ]
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Clear entity manager to force reload from DB
            $em->clear();

            // Verify in database
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            $this->assertNotNull($planDeCroissance);

            $goals = [];
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                    $goals[] = $gg;
                }
            }

            $this->assertCount(3, $goals);

            // Sort by title to ensure deterministic checks
            usort($goals, fn($a, $b) => strcmp($a->getTitle(), $b->getTitle()));

            // 'Architecture des ordinateurs' (CONNAISSANCE, build)
            $this->assertEquals('Architecture des ordinateurs', $goals[0]->getTitle());
            $this->assertEquals('CONNAISSANCE', $goals[0]->getType());
            $this->assertEquals('build', $goals[0]->getSubtype());
            $this->assertEquals('Lire des articles de blog', $goals[0]->getStrategy());
            $this->assertCount(2, $goals[0]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $goals[0]->getSupportedLifeGoals()[0]->getId());
            $this->assertEquals($lgId2, $goals[0]->getSupportedLifeGoals()[1]->getId());

            // 'Négocier des contrats' (COMPETENCE, leverage)
            $this->assertEquals('Négocier des contrats', $goals[1]->getTitle());
            $this->assertEquals('COMPETENCE', $goals[1]->getType());
            $this->assertEquals('leverage', $goals[1]->getSubtype());
            $this->assertEquals('Pratique réelle', $goals[1]->getStrategy());
            $this->assertCount(1, $goals[1]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $goals[1]->getSupportedLifeGoals()[0]->getId());

            // 'Programmer en PHP' (COMPETENCE, consolidate)
            $this->assertEquals('Programmer en PHP', $goals[2]->getTitle());
            $this->assertEquals('COMPETENCE', $goals[2]->getType());
            $this->assertEquals('consolidate', $goals[2]->getSubtype());
            $this->assertEquals('Suivre des cours Symfony', $goals[2]->getStrategy());
            $this->assertCount(1, $goals[2]->getSupportedLifeGoals());
            $this->assertEquals($lgId2, $goals[2]->getSupportedLifeGoals()[0]->getId());

        } finally {
            // Restore backup goals
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            if ($planDeCroissance) {
                foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                    if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE) {
                        $em->remove($gg);
                    }
                }
                $em->flush();

                foreach ($backupGoals as $bg) {
                    $gg = new \App\PlanDeCroissance\Entity\GrowthGoal();
                    $gg->setPlanDeCroissance($planDeCroissance);
                    $gg->setTitle($bg['title']);
                    $gg->setStrategy($bg['strategy']);
                    $gg->setMetric($bg['metric'] ?: '');
                    $gg->setProgress($bg['progress'] ?: 0);
                    $gg->setDeadline($bg['deadline']);
                    $gg->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE);
                    $gg->setType($bg['type']);
                    $gg->setSubtype($bg['subtype']);
                    $em->persist($gg);
                }
                $em->flush();
            }
        }
    }

    public function testSaveGrowthGoalsSteps9And10(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDeCroissance = $testUser->getPlanDeCroissance();

        // Backup existing CHARACTER and RELATION growth goals to restore them later
        $backupGoals = [];
        if ($planDeCroissance) {
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER || $gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::RELATION) {
                    $backupGoals[] = [
                        'title' => $gg->getTitle(),
                        'strategy' => $gg->getStrategy(),
                        'metric' => $gg->getMetric(),
                        'progress' => $gg->getProgress(),
                        'deadline' => $gg->getDeadline(),
                        'axis' => $gg->getAxis(),
                        'type' => $gg->getType(),
                        'subtype' => $gg->getSubtype(),
                    ];
                }
            }
        }
        
        try {
            $client->loginUser($testUser);

            $lifeGoals = $testUser->getPlanDeVie()->getLifeGoals();
            $lgId1 = $lifeGoals[0]->getId();
            
            // Post Step 9 data
            $client->request('POST', '/plan-de-vie/assistant?step=9&single=1', [
                'growth_character_title' => ['Faire preuve de patience'],
                'growth_character_strategy' => ['Méditer chaque matin'],
                'growth_character_status' => ['consolidate'],
                'growth_character_goals' => [
                    0 => [$lgId1]
                ]
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Post Step 10 data
            $client->request('POST', '/plan-de-vie/assistant?step=10&single=1', [
                'growth_relation_title' => ['Trouver un mentor technique'],
                'growth_relation_strategy' => ['Assister aux meetups'],
                'growth_relation_status' => ['build'],
                'growth_relation_type' => ['orientation'],
                'growth_relation_goals' => [
                    0 => [$lgId1]
                ]
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Clear entity manager to force reload from DB
            $em->clear();

            // Verify in database
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            $this->assertNotNull($planDeCroissance);

            $charGoals = [];
            $relGoals = [];
            foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER) {
                    $charGoals[] = $gg;
                } elseif ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::RELATION) {
                    $relGoals[] = $gg;
                }
            }

            $this->assertCount(1, $charGoals);
            $this->assertEquals('Faire preuve de patience', $charGoals[0]->getTitle());
            $this->assertEquals('consolidate', $charGoals[0]->getSubtype());
            $this->assertCount(1, $charGoals[0]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $charGoals[0]->getSupportedLifeGoals()[0]->getId());

            $this->assertCount(1, $relGoals);
            $this->assertEquals('Trouver un mentor technique', $relGoals[0]->getTitle());
            $this->assertEquals('build', $relGoals[0]->getSubtype());
            $this->assertEquals('orientation', $relGoals[0]->getType());
            $this->assertCount(1, $relGoals[0]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $relGoals[0]->getSupportedLifeGoals()[0]->getId());

        } finally {
            // Restore backup goals
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            if ($planDeCroissance) {
                foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                    if ($gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER || $gg->getAxis() === \App\PlanDeCroissance\Enum\GrowthAxis::RELATION) {
                        $em->remove($gg);
                    }
                }
                $em->flush();

                foreach ($backupGoals as $bg) {
                    $gg = new \App\PlanDeCroissance\Entity\GrowthGoal();
                    $gg->setPlanDeCroissance($planDeCroissance);
                    $gg->setTitle($bg['title']);
                    $gg->setStrategy($bg['strategy']);
                    $gg->setMetric($bg['metric'] ?: '');
                    $gg->setProgress($bg['progress'] ?: 0);
                    $gg->setDeadline($bg['deadline']);
                    $gg->setAxis($bg['axis']);
                    $gg->setType($bg['type']);
                    $gg->setSubtype($bg['subtype']);
                    $em->persist($gg);
                }
                $em->flush();
            }
        }
    }

    public function testSaveLegacyGoalsStep11(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDeTransmission = $testUser->getPlanDeTransmission();

        // Backup existing LegacyGoals
        $backupGoals = [];
        if ($planDeTransmission) {
            foreach ($planDeTransmission->getLegacyGoals() as $lg) {
                $backupGoals[] = [
                    'title' => $lg->getTitle(),
                    'subtype' => $lg->getSubtype(),
                    'targetBeneficiary' => $lg->getTargetBeneficiary(),
                    'supportedLifeGoals' => array_map(fn($g) => $g->getId(), $lg->getSupportedLifeGoals()->toArray()),
                ];
            }
        }
        
        try {
            $client->loginUser($testUser);

            $lifeGoals = $testUser->getPlanDeVie()->getLifeGoals();
            $lgId1 = $lifeGoals[0]->getId();
            
            // Post Step 11 data
            $client->request('POST', '/plan-de-vie/assistant?step=11&single=1', [
                'legacy_title' => ['Fonder une académie de leadership'],
                'legacy_status' => ['leverage'],
                'legacy_goals' => [
                    0 => [$lgId1]
                ]
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Clear entity manager to force reload from DB
            $em->clear();

            // Verify in database
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeTransmission = $testUser->getPlanDeTransmission();
            $this->assertNotNull($planDeTransmission);

            $legacyGoals = $planDeTransmission->getLegacyGoals();
            $this->assertCount(1, $legacyGoals);
            $this->assertEquals('Fonder une académie de leadership', $legacyGoals[0]->getTitle());
            $this->assertEquals('leverage', $legacyGoals[0]->getSubtype());
            $this->assertCount(1, $legacyGoals[0]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $legacyGoals[0]->getSupportedLifeGoals()[0]->getId());

        } finally {
            // Restore backup
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeTransmission = $testUser->getPlanDeTransmission();
            if ($planDeTransmission) {
                foreach ($planDeTransmission->getLegacyGoals() as $lg) {
                    $em->remove($lg);
                }
                $em->flush();

                foreach ($backupGoals as $bg) {
                    $lg = new \App\PlanDeTransmission\Entity\LegacyGoal();
                    $lg->setPlanDeTransmission($planDeTransmission);
                    $lg->setTitle($bg['title']);
                    $lg->setSubtype($bg['subtype']);
                    $lg->setTargetBeneficiary($bg['targetBeneficiary']);
                    foreach ($bg['supportedLifeGoals'] as $lgId) {
                        $lifeGoal = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                        if ($lifeGoal) {
                            $lg->addSupportedLifeGoal($lifeGoal);
                        }
                    }
                    $em->persist($lg);
                }
                $em->flush();
            }
        }
    }

    public function testSaveHygieneDomainsStep12(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $planDHygiene = $testUser->getPlanDHygiene();

        // Backup existing HygieneDomains
        $backupDomains = [];
        if ($planDHygiene) {
            foreach ($planDHygiene->getHygieneDomains() as $hd) {
                $backupDomains[] = [
                    'name' => $hd->getName(),
                    'subtype' => $hd->getSubtype(),
                    'supportedLifeGoals' => array_map(fn($g) => $g->getId(), $hd->getSupportedLifeGoals()->toArray()),
                ];
            }
        }
        
        try {
            $client->loginUser($testUser);

            $lifeGoals = $testUser->getPlanDeVie()->getLifeGoals();
            $lgId1 = $lifeGoals[0]->getId();
            
            // Post Step 12 data
            $client->request('POST', '/plan-de-vie/assistant?step=12&single=1', [
                'hygiene_name' => ['Physique (Sommeil/Nutrition)'],
                'hygiene_status' => ['consolidate'],
                'hygiene_goals' => [
                    0 => [$lgId1]
                ]
            ]);

            $this->assertResponseRedirects('/plan-de-vie');

            // Clear entity manager to force reload from DB
            $em->clear();

            // Verify in database
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDHygiene = $testUser->getPlanDHygiene();
            $this->assertNotNull($planDHygiene);

            $hygieneDomains = $planDHygiene->getHygieneDomains();
            $this->assertCount(1, $hygieneDomains);
            $this->assertEquals('Physique (Sommeil/Nutrition)', $hygieneDomains[0]->getName());
            $this->assertEquals('consolidate', $hygieneDomains[0]->getSubtype());
            $this->assertCount(1, $hygieneDomains[0]->getSupportedLifeGoals());
            $this->assertEquals($lgId1, $hygieneDomains[0]->getSupportedLifeGoals()[0]->getId());

        } finally {
            // Restore backup
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDHygiene = $testUser->getPlanDHygiene();
            if ($planDHygiene) {
                foreach ($planDHygiene->getHygieneDomains() as $hd) {
                    $em->remove($hd);
                }
                $em->flush();

                foreach ($backupDomains as $bd) {
                    $hd = new \App\PlanDHygiene\Entity\HygieneDomain();
                    $hd->setPlanDHygiene($planDHygiene);
                    $hd->setName($bd['name']);
                    $hd->setSubtype($bd['subtype']);
                    foreach ($bd['supportedLifeGoals'] as $lgId) {
                        $lifeGoal = $em->getRepository(\App\PlanDeVie\Entity\LifeGoal::class)->find($lgId);
                        if ($lifeGoal) {
                            $hd->addSupportedLifeGoal($lifeGoal);
                        }
                    }
                    $em->persist($hd);
                }
                $em->flush();
            }
        }
    }

    public function testSaveCroissanceAxis(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // 1. Create temporary internal balance
        $ib = new \App\PlanDeVie\Entity\InternalBalance();
        $ib->setUser($testUser);
        $ib->setType('LIMITING_BELIEF');
        $ib->setText('Croyance de test');
        $em->persist($ib);
        
        // 2. Fetch a life goal to link
        $lifeGoals = $testUser->getPlanDeVie()->getLifeGoals();
        $lgId = $lifeGoals[0]->getId();

        $em->flush();

        $ibId = $ib->getId();

        try {
            // Post to the route
            $client->request('POST', "/plan-de-croissance/axe/LIMITING_BELIEF/{$ibId}", [
                'status' => 'consolidate',
                'strategy' => 'Une stratégie de croissance',
                'life_goals' => [$lgId],
                'action_titles' => ['Action 1', 'Action 2'],
                'action_deadlines' => ['2026-12-31', ''],
                'habit_titles' => ['Habitude 1'],
                'habit_frequencies' => ['WEEKLY']
            ]);

            $this->assertResponseRedirects('/plan-de-croissance?tab=SYSTEM');

            // Clear to reload from DB
            $em->clear();

            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            $this->assertNotNull($planDeCroissance);

            // Find the goal linked to the limiting belief
            $goal = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoal::class)->findOneBy([
                'planDeCroissance' => $planDeCroissance,
                'sourceType' => 'LIMITING_BELIEF',
                'sourceId' => $ibId
            ]);

            $this->assertNotNull($goal);
            $this->assertEquals('Croyance de test', $goal->getTitle());
            $this->assertEquals('Une stratégie de croissance', $goal->getStrategy());
            $this->assertEquals('consolidate', $goal->getSubtype());
            $this->assertCount(1, $goal->getSupportedLifeGoals());
            $this->assertEquals($lgId, $goal->getSupportedLifeGoals()[0]->getId());

            $actions = $goal->getActions();
            $this->assertCount(2, $actions);
            // Sort by title
            $actionsArr = $actions->toArray();
            usort($actionsArr, fn($a, $b) => strcmp($a->getTitle(), $b->getTitle()));
            $this->assertEquals('Action 1', $actionsArr[0]->getTitle());
            $this->assertNotNull($actionsArr[0]->getDeadline());
            $this->assertEquals('2026-12-31', $actionsArr[0]->getDeadline()->format('Y-m-d'));
            $this->assertEquals('Action 2', $actionsArr[1]->getTitle());
            $this->assertNull($actionsArr[1]->getDeadline());

            $habits = $goal->getHabits();
            $this->assertCount(1, $habits);
            $this->assertEquals('Habitude 1', $habits[0]->getTitle());
            $this->assertEquals('WEEKLY', $habits[0]->getFrequency()->value);

        } finally {
            // Cleanup
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            $planDeCroissance = $testUser->getPlanDeCroissance();
            if ($planDeCroissance) {
                $goal = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoal::class)->findOneBy([
                    'planDeCroissance' => $planDeCroissance,
                    'sourceType' => 'LIMITING_BELIEF',
                    'sourceId' => $ibId
                ]);
                if ($goal) {
                    $em->remove($goal);
                }
            }
            $ibEntity = $em->getRepository(\App\PlanDeVie\Entity\InternalBalance::class)->find($ibId);
            if ($ibEntity) {
                $em->remove($ibEntity);
            }
            $em->flush();
        }
    }

    public function testSaveCroissancePrinciples(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // Backup existing principles of type CROISSANCE
        $backupPrinciples = [];
        foreach ($testUser->getPrinciples() as $pr) {
            if ($pr->getPillar() === 'CROISSANCE') {
                $backupPrinciples[] = [
                    'title' => $pr->getTitle(),
                    'description' => $pr->getDescription(),
                ];
            }
        }

        try {
            // Post to the save principles route
            $client->request('POST', '/principes/CROISSANCE', [
                'proposed_principles' => ['syst-obj', 'one-thing'],
                'custom_principle_titles' => ['Mon principe perso', ''],
                'custom_principle_descriptions' => ['Explication de mon principe', '']
            ]);

            $this->assertResponseRedirects('/plan-de-croissance?tab=LAWS');

            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');

            $principles = [];
            foreach ($testUser->getPrinciples() as $pr) {
                if ($pr->getPillar() === 'CROISSANCE') {
                    $principles[] = $pr;
                }
            }

            $this->assertCount(3, $principles);
            
            // Sort by title
            usort($principles, fn($a, $b) => strcmp($a->getTitle(), $b->getTitle()));

            $this->assertEquals('Mon principe perso', $principles[0]->getTitle());
            $this->assertEquals('Explication de mon principe', $principles[0]->getDescription());
            $this->assertEquals('Systèmes > Objectifs', $principles[1]->getTitle());
            $this->assertEquals('Une chose à la fois', $principles[2]->getTitle());

        } finally {
            // Restore backup
            $em->clear();
            $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
            foreach ($testUser->getPrinciples() as $pr) {
                if ($pr->getPillar() === 'CROISSANCE') {
                    $em->remove($pr);
                }
            }
            $em->flush();

            foreach ($backupPrinciples as $bp) {
                $pr = new \App\PlanDeCroissance\Entity\Principle();
                $pr->setUser($testUser);
                $pr->setTitle($bp['title']);
                $pr->setDescription($bp['description']);
                $pr->setPillar('CROISSANCE');
                $em->persist($pr);
            }
            $em->flush();
        }
    }

    public function testCroissancePlanVisualize(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $client->request('GET', '/plan-de-croissance/visualiser');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Croissance');
        $this->assertSelectorTextContains('body', 'Intention & Raison d\'être');
        $this->assertSelectorTextContains('body', 'Axes de croissance');
    }

    public function testCroissancePlanSendEmail(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $client->request('POST', '/plan-de-croissance/email');

        $this->assertResponseIsSuccessful();
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent);
        
        $data = json_decode($responseContent, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('admin@kiinisawa.com', $data['emailSentTo']);
    }

    public function testWizardFiltersStepsByPlan(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // Request wizard for CROISSANCE
        $client->request('GET', '/plan-de-vie/assistant?plan=CROISSANCE');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Croissance');
        $this->assertSelectorNotExists('div[data-step-title="But de Dieu"]');
        $this->assertSelectorExists('div[data-step-title="Balance interne"]');
        $this->assertSelectorExists('div[data-step-title="Caractère & Conviction"]');
        $this->assertSelectorExists('div[data-step-title="Relation & Réseau"]');

        // Request wizard for DEVELOPPEMENT
        $client->request('GET', '/plan-de-vie/assistant?plan=DEVELOPPEMENT');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Développement');
        $this->assertSelectorExists('div[data-step-title="Compétence & Connaissance"]');
        $this->assertSelectorNotExists('div[data-step-title="But de Dieu"]');

        // Request wizard for TRANSMISSION
        $client->request('GET', '/plan-de-vie/assistant?plan=TRANSMISSION');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Transmission');
        $this->assertSelectorNotExists('div[data-step-title="Relation & Réseau"]', 'Relation & Réseau est un axe du Plan de Croissance (charte §1bis)');
        $this->assertSelectorExists('div[data-step-title="Héritage & Transmission"]');

        // Request wizard for HYGIENE
        $client->request('GET', '/plan-de-vie/assistant?plan=HYGIENE');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan d\'Hygiène de vie');
        $this->assertSelectorExists('div[data-step-title="Hygiène de Vie"]');
    }

    public function testSaveOtherPlansPrinciples(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // 1. Save DEVELOPPEMENT principles
        $client->request('POST', '/principes/DEVELOPPEMENT', [
            'proposed_principles' => ['syst-obj'],
            'custom_principle_titles' => ['Perso Dev', ''],
            'custom_principle_descriptions' => ['Desc Dev', '']
        ]);
        $this->assertResponseRedirects('/plan-de-developpement?tab=LAWS');

        $em->clear();
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $devPrinciples = array_filter($testUser->getPrinciples()->toArray(), fn($p) => $p->getPillar() === 'DEVELOPPEMENT');
        $this->assertCount(2, $devPrinciples);

        // Clean up
        foreach ($devPrinciples as $p) {
            $em->remove($p);
        }
        $em->flush();
    }

    public function testOtherPlansVisualizeAndEmail(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // Test DEVELOPPEMENT
        $client->request('GET', '/plan-de-developpement/visualiser');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Développement');

        $client->request('POST', '/plan-de-developpement/email');
        $this->assertResponseIsSuccessful();
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent);
        $data = json_decode($responseContent, true);
        $this->assertTrue($data['success']);

        // Test TRANSMISSION
        $client->request('GET', '/plan-de-transmission/visualiser');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan de Transmission');

        $client->request('POST', '/plan-de-transmission/email');
        $this->assertResponseIsSuccessful();
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent);
        $data = json_decode($responseContent, true);
        $this->assertTrue($data['success']);

        // Test HYGIENE
        $client->request('GET', '/plan-d-hygiene/visualiser');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Plan d\'Hygiène de vie');

        $client->request('POST', '/plan-d-hygiene/email');
        $this->assertResponseIsSuccessful();
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent);
        $data = json_decode($responseContent, true);
        $this->assertTrue($data['success']);
    }
}



