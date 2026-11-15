<?php

namespace App\Personnalite\Controller\Admin;

use App\Personnalite\Entity\PersonalityAnswer;
use App\Personnalite\Entity\PersonalityProfile;
use App\Personnalite\Entity\PersonalityQuestion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tests')]
#[IsGranted('ROLE_ADMIN')]
class PersonalityAdminController extends AbstractController
{
    private const TESTS = [
        'INTELLIGENCES' => ['name' => 'Intelligences Multiples', 'subtitle' => 'Théorie de Gardner', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
        'TALENT'        => ['name' => 'Architecture des Talents', 'subtitle' => 'Zones de Génie', 'color' => '#f97316', 'bg' => '#fff7ed'],
        'EQUILIBRE'     => ['name' => 'Équilibre de Vie', 'subtitle' => 'Wheel of Life', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
        'HYGIENE'       => ['name' => 'Hygiène de Vie', 'subtitle' => '6 Axes Vitaux', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
    ];

    #[Route('', name: 'admin_personality_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $stats = [];
        foreach (self::TESTS as $type => $meta) {
            $questionCount = $em->getRepository(PersonalityQuestion::class)->count(['testType' => $type]);
            $profileCount = $em->getRepository(PersonalityProfile::class)->count(['testType' => $type]);
            $stats[$type] = array_merge($meta, [
                'type' => $type,
                'questions' => $questionCount,
                'profiles' => $profileCount,
            ]);
        }

        return $this->render('personnalite/admin/index.html.twig', [
            'tests' => $stats,
        ]);
    }

    #[Route('/{type}/questions', name: 'admin_personality_questions')]
    public function questions(string $type, Request $request, EntityManagerInterface $em): Response
    {
        $type = strtoupper($type);
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        $questions = $em->getRepository(PersonalityQuestion::class)->findBy(
            ['testType' => $type],
            ['sortOrder' => 'ASC']
        );

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('add_question', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($type)]);
            }

            $questionText = $request->request->get('question');
            $category = $request->request->get('category');
            $sortOrder = $request->request->getInt('sortOrder', 1);
            $answersText = $request->request->get('answers');

            if (empty($questionText)) {
                $this->addFlash('error', 'Le texte de la question est obligatoire.');
                return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($type)]);
            }

            $q = new PersonalityQuestion();
            $q->setTestType($type);
            $q->setQuestion($questionText);
            $q->setCategory($category);
            $q->setSortOrder($sortOrder);

            $em->persist($q);

            // Parse and save answers
            $this->parseAndSaveAnswers($q, $answersText, $em);

            $em->flush();
            $this->addFlash('success', 'Question ajoutée avec succès.');
            return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($type)]);
        }

        return $this->render('personnalite/admin/questions.html.twig', [
            'testMeta' => self::TESTS[$type],
            'type' => $type,
            'questions' => $questions,
        ]);
    }

    #[Route('/question/{id}/edit', name: 'admin_personality_question_edit', methods: ['POST'])]
    public function editQuestion(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $q = $em->getRepository(PersonalityQuestion::class)->find($id);
        if (!$q) {
            $this->addFlash('error', 'Question introuvable.');
            return $this->redirectToRoute('admin_personality_dashboard');
        }

        if (!$this->isCsrfTokenValid('edit_question_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($q->getTestType())]);
        }

        $questionText = $request->request->get('question');
        $category = $request->request->get('category');
        $sortOrder = $request->request->getInt('sortOrder', 1);
        $answersText = $request->request->get('answers');

        if (empty($questionText)) {
            $this->addFlash('error', 'Le texte de la question est obligatoire.');
            return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($q->getTestType())]);
        }

        $q->setQuestion($questionText);
        $q->setCategory($category);
        $q->setSortOrder($sortOrder);

        // Clear existing answers first
        foreach ($q->getAnswers() as $ans) {
            $em->remove($ans);
        }
        $em->flush();

        // Parse and save new answers
        $this->parseAndSaveAnswers($q, $answersText, $em);

        $em->flush();
        $this->addFlash('success', 'Question mise à jour avec succès.');
        return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($q->getTestType())]);
    }

    #[Route('/question/{id}/delete', name: 'admin_personality_question_delete', methods: ['POST'])]
    public function deleteQuestion(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $q = $em->getRepository(PersonalityQuestion::class)->find($id);
        if (!$q) {
            $this->addFlash('error', 'Question introuvable.');
            return $this->redirectToRoute('admin_personality_dashboard');
        }

        if (!$this->isCsrfTokenValid('delete_question_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($q->getTestType())]);
        }

        $type = $q->getTestType();
        $em->remove($q);
        $em->flush();

        $this->addFlash('success', 'Question supprimée avec succès.');
        return $this->redirectToRoute('admin_personality_questions', ['type' => strtolower($type)]);
    }

    #[Route('/{type}/profils', name: 'admin_personality_profiles')]
    public function profiles(string $type, Request $request, EntityManagerInterface $em): Response
    {
        $type = strtoupper($type);
        if (!isset(self::TESTS[$type])) {
            throw $this->createNotFoundException("Test inconnu : $type");
        }

        $profiles = $em->getRepository(PersonalityProfile::class)->findBy(
            ['testType' => $type],
            ['code' => 'ASC']
        );

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('add_profile', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($type)]);
            }

            $code = $request->request->get('code');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $strengths = $request->request->get('strengths');
            $weaknesses = $request->request->get('weaknesses');
            $essence = $request->request->get('essence');
            $relationships = $request->request->get('relationships');
            $career = $request->request->get('career');
            $emotionalDynamics = $request->request->get('emotionalDynamics');
            $growthAxes = $request->request->get('growthAxes');

            if (empty($code) || empty($title) || empty($description)) {
                $this->addFlash('error', 'Le code, le titre et la description sont obligatoires.');
                return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($type)]);
            }

            $p = new PersonalityProfile();
            $p->setTestType($type);
            $p->setCode(strtoupper(trim($code)));
            $p->setTitle(trim($title));
            $p->setDescription(trim($description));
            $p->setStrengths(trim($strengths));
            $p->setWeaknesses(trim($weaknesses));
            $p->setEssence(trim($essence));
            $p->setRelationships(trim($relationships));
            $p->setCareer(trim($career));
            $p->setEmotionalDynamics(trim($emotionalDynamics));
            $p->setGrowthAxes(trim($growthAxes));

            $em->persist($p);
            $em->flush();

            $this->addFlash('success', 'Profil de résultat ajouté avec succès.');
            return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($type)]);
        }

        return $this->render('personnalite/admin/profiles.html.twig', [
            'testMeta' => self::TESTS[$type],
            'type' => $type,
            'profiles' => $profiles,
        ]);
    }

    #[Route('/profile/{id}/edit', name: 'admin_personality_profile_edit', methods: ['POST'])]
    public function editProfile(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $p = $em->getRepository(PersonalityProfile::class)->find($id);
        if (!$p) {
            $this->addFlash('error', 'Profil de résultat introuvable.');
            return $this->redirectToRoute('admin_personality_dashboard');
        }

        if (!$this->isCsrfTokenValid('edit_profile_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($p->getTestType())]);
        }

        $code = $request->request->get('code');
        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $strengths = $request->request->get('strengths');
        $weaknesses = $request->request->get('weaknesses');
        $essence = $request->request->get('essence');
        $relationships = $request->request->get('relationships');
        $career = $request->request->get('career');
        $emotionalDynamics = $request->request->get('emotionalDynamics');
        $growthAxes = $request->request->get('growthAxes');

        if (empty($code) || empty($title) || empty($description)) {
            $this->addFlash('error', 'Le code, le titre et la description sont obligatoires.');
            return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($p->getTestType())]);
        }

        $p->setCode(strtoupper(trim($code)));
        $p->setTitle(trim($title));
        $p->setDescription(trim($description));
        $p->setStrengths(trim($strengths));
        $p->setWeaknesses(trim($weaknesses));
        $p->setEssence(trim($essence));
        $p->setRelationships(trim($relationships));
        $p->setCareer(trim($career));
        $p->setEmotionalDynamics(trim($emotionalDynamics));
        $p->setGrowthAxes(trim($growthAxes));

        $em->flush();
        $this->addFlash('success', 'Profil de résultat mis à jour avec succès.');
        return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($p->getTestType())]);
    }

    #[Route('/profile/{id}/delete', name: 'admin_personality_profile_delete', methods: ['POST'])]
    public function deleteProfile(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $p = $em->getRepository(PersonalityProfile::class)->find($id);
        if (!$p) {
            $this->addFlash('error', 'Profil de résultat introuvable.');
            return $this->redirectToRoute('admin_personality_dashboard');
        }

        if (!$this->isCsrfTokenValid('delete_profile_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($p->getTestType())]);
        }

        $type = $p->getTestType();
        $em->remove($p);
        $em->flush();

        $this->addFlash('success', 'Profil de résultat supprimé avec succès.');
        return $this->redirectToRoute('admin_personality_profiles', ['type' => strtolower($type)]);
    }

    private function parseAndSaveAnswers(PersonalityQuestion $q, ?string $answersText, EntityManagerInterface $em): void
    {
        if (empty($answersText)) {
            return;
        }

        $lines = explode("\n", str_replace("\r", "", $answersText));
        $sortOrder = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $ans = new PersonalityAnswer();
                $ans->setQuestion($q);
                $ans->setValue(trim($parts[0]));
                $ans->setText(trim($parts[1]));
                $ans->setSortOrder($sortOrder++);
                $em->persist($ans);
            }
        }
    }
}
