<?php

namespace App\PlanDeVie\Controller\Admin;

use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Vision;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Entity\Passion;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\LifeMilestone;
use App\PlanDeVie\Form\PurposeType;
use App\PlanDeVie\Form\VisionType;
use App\PlanDeVie\Repository\LifeGoalRepository;
use App\PlanDeVie\Repository\LifeMilestoneRepository;
use App\PlanDeVie\Repository\ValueRepository;
use App\PlanDeVie\Repository\PassionRepository;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Repository\InternalBalanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plan-de-vie')]
#[IsGranted('ROLE_ADMIN')]
class PlanDeVieController extends AbstractController
{
    #[Route('', name: 'admin_plan_de_vie', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();

        // Auto-create PlanDeVie if it doesn't exist
        if (!$planDeVie) {
            $planDeVie = new PlanDeVie();
            $planDeVie->setUser($user);
            $em->persist($planDeVie);
            $em->flush();
        }

        // Auto-create Purpose if it doesn't exist
        $purpose = $planDeVie->getPurpose();
        if (!$purpose) {
            $purpose = new Purpose();
            $purpose->setPlanDeVie($planDeVie);
            $purpose->setStatement('');
            $em->persist($purpose);
            $em->flush();
        }

        // Auto-create Vision if it doesn't exist
        $vision = $planDeVie->getVision();
        if (!$vision) {
            $vision = new Vision();
            $vision->setPlanDeVie($planDeVie);
            $vision->setContent('');
            $vision->setHorizon('');
            $vision->setBeneficiaries('');
            $vision->setIndicators('');
            $em->persist($vision);
            $em->flush();
        }

        $purposeForm = $this->createForm(PurposeType::class, $purpose, [
            'action' => $this->generateUrl('admin_plan_de_vie_update_purpose'),
        ]);

        $visionForm = $this->createForm(VisionType::class, $vision, [
            'action' => $this->generateUrl('admin_plan_de_vie_update_vision'),
        ]);

        $valuesReal = [];
        $valuesDeclared = [];
        $valuesCounter = [];
        foreach ($planDeVie->getValues() as $v) {
            if ($v->getCategory() === 'declared') {
                $valuesDeclared[] = $v;
            } elseif ($v->getCategory() === 'counter') {
                $valuesCounter[] = $v;
            } else {
                $valuesReal[] = $v;
            }
        }

        $internalBalances = $user->getInternalBalances();
        $convictionsDeep = [];
        $convictionsMedium = [];
        $convictionsLight = [];
        $limitingBeliefs = [];
        foreach ($internalBalances as $ib) {
            if ($ib->getType() === 'CONVICTION') {
                if ($ib->getSubtype() === 'medium') {
                    $convictionsMedium[] = $ib;
                } elseif ($ib->getSubtype() === 'light') {
                    $convictionsLight[] = $ib;
                } else {
                    $convictionsDeep[] = $ib;
                }
            } elseif ($ib->getType() === 'LIMITING_BELIEF') {
                $limitingBeliefs[] = $ib;
            }
        }

        return $this->render('plan_de_vie/admin/index.html.twig', [
            'planDeVie' => $planDeVie,
            'purposeForm' => $purposeForm->createView(),
            'visionForm' => $visionForm->createView(),
            'valuesReal' => $valuesReal,
            'valuesDeclared' => $valuesDeclared,
            'valuesCounter' => $valuesCounter,
            'convictionsDeep' => $convictionsDeep,
            'convictionsMedium' => $convictionsMedium,
            'convictionsLight' => $convictionsLight,
            'limitingBeliefs' => $limitingBeliefs,
        ]);
    }

    #[Route('/purpose', name: 'admin_plan_de_vie_update_purpose', methods: ['POST'])]
    public function updatePurpose(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();
        $purpose = $planDeVie?->getPurpose();

        if (!$purpose) {
            $this->addFlash('error', 'Plan ou mission introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $form = $this->createForm(PurposeType::class, $purpose);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre mission de vie a été mise à jour avec succès.');
        } else {
            $this->addFlash('error', 'Erreur lors de la mise à jour de la mission.');
        }

        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/vision', name: 'admin_plan_de_vie_update_vision', methods: ['POST'])]
    public function updateVision(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();
        $vision = $planDeVie?->getVision();

        if (!$vision) {
            $this->addFlash('error', 'Plan ou vision introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $form = $this->createForm(VisionType::class, $vision);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre vision a été mise à jour avec succès.');
        } else {
            $this->addFlash('error', 'Erreur lors de la mise à jour de la vision.');
        }

        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/value/add', name: 'admin_plan_de_vie_add_value', methods: ['POST'])]
    public function addValue(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_value', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();

        $name = $request->request->get('name');
        $behavior = $request->request->get('behavior');
        $category = $request->request->get('category', 'real');
        if (!in_array($category, ['real', 'declared', 'counter'], true)) {
            $category = 'real';
        }

        if (empty($name)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $value = new Value();
        $value->setPlanDeVie($planDeVie);
        $value->setName($name);
        $value->setBehavior($behavior ?: '');
        $value->setCategory($category);

        $em->persist($value);
        $em->flush();

        $this->addFlash('success', 'Valeur ajoutée avec succès.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/value/{id}/edit', name: 'admin_plan_de_vie_edit_value', methods: ['POST'])]
    public function editValue(int $id, Request $request, ValueRepository $valueRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('edit_value_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $value = $valueRepo->find($id);

        if (!$value || $value->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Valeur introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $name = $request->request->get('name');
        $behavior = $request->request->get('behavior');
        $category = $request->request->get('category', 'real');
        if (!in_array($category, ['real', 'declared', 'counter'], true)) {
            $category = 'real';
        }

        if (empty($name)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $value->setName($name);
        $value->setBehavior($behavior ?: '');
        $value->setCategory($category);
        $em->flush();

        $this->addFlash('success', 'Valeur mise à jour.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/value/{id}/delete', name: 'admin_plan_de_vie_delete_value', methods: ['POST'])]
    public function deleteValue(int $id, Request $request, ValueRepository $valueRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_value_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $value = $valueRepo->find($id);

        if (!$value || $value->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Valeur introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $em->remove($value);
        $em->flush();

        $this->addFlash('success', 'Valeur supprimée.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/passion/add', name: 'admin_plan_de_vie_add_passion', methods: ['POST'])]
    public function addPassion(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_passion', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();

        $name = $request->request->get('name');
        $context = $request->request->get('context');

        if (empty($name)) {
            $this->addFlash('error', 'Le nom de la passion est obligatoire.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $passion = new Passion();
        $passion->setPlanDeVie($planDeVie);
        $passion->setName($name);
        $passion->setContext($context);

        $em->persist($passion);
        $em->flush();

        $this->addFlash('success', 'Passion ajoutée avec succès.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/passion/{id}/edit', name: 'admin_plan_de_vie_edit_passion', methods: ['POST'])]
    public function editPassion(int $id, Request $request, PassionRepository $passionRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('edit_passion_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $passion = $passionRepo->find($id);

        if (!$passion || $passion->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Passion introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $name = $request->request->get('name');
        $context = $request->request->get('context');

        if (empty($name)) {
            $this->addFlash('error', 'Le nom de la passion est obligatoire.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $passion->setName($name);
        $passion->setContext($context);
        $em->flush();

        $this->addFlash('success', 'Passion mise à jour.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/passion/{id}/delete', name: 'admin_plan_de_vie_delete_passion', methods: ['POST'])]
    public function deletePassion(int $id, Request $request, PassionRepository $passionRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_passion_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $passion = $passionRepo->find($id);

        if (!$passion || $passion->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Passion introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $em->remove($passion);
        $em->flush();

        $this->addFlash('success', 'Passion supprimée.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/goal/add', name: 'admin_plan_de_vie_add_goal', methods: ['POST'])]
    public function addLifeGoal(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_goal', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $user = $this->getUser();
        $planDeVie = $user->getPlanDeVie();

        $title = $request->request->get('title');
        $category = $request->request->get('category');
        $priority = $request->request->get('priority');
        $metric = $request->request->get('metric');
        $deadlineStr = $request->request->get('deadline');

        if (empty($title) || empty($category) || empty($priority)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires de l\'objectif.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal = new LifeGoal();
        $goal->setPlanDeVie($planDeVie);
        $goal->setTitle($title);
        $goal->setCategory($category);
        $goal->setPriority($priority);
        $goal->setMetric($metric);
        $goal->setProgress(0);

        if (!empty($deadlineStr)) {
            $goal->setDeadline(new \DateTime($deadlineStr));
        }

        $em->persist($goal);
        $em->flush();

        $this->addFlash('success', 'Objectif de vie créé avec succès.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/goal/{id}/edit', name: 'admin_plan_de_vie_edit_goal', methods: ['POST'])]
    public function editLifeGoal(int $id, Request $request, LifeGoalRepository $goalRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('edit_goal_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal = $goalRepo->find($id);

        if (!$goal || $goal->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Objectif de vie introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $title = $request->request->get('title');
        $category = $request->request->get('category');
        $priority = $request->request->get('priority');
        $metric = $request->request->get('metric');
        $deadlineStr = $request->request->get('deadline');
        $progress = $request->request->get('progress');

        if (empty($title) || empty($category) || empty($priority)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal->setTitle($title);
        $goal->setCategory($category);
        $goal->setPriority($priority);
        $goal->setMetric($metric);
        
        if ($progress !== null) {
            $goal->setProgress((int) $progress);
        }

        if (!empty($deadlineStr)) {
            $goal->setDeadline(new \DateTime($deadlineStr));
        } else {
            $goal->setDeadline(null);
        }

        $em->flush();

        $this->addFlash('success', 'Objectif de vie mis à jour.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/goal/{id}/delete', name: 'admin_plan_de_vie_delete_goal', methods: ['POST'])]
    public function deleteLifeGoal(int $id, Request $request, LifeGoalRepository $goalRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_goal_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal = $goalRepo->find($id);

        if (!$goal || $goal->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Objectif de vie introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $em->remove($goal);
        $em->flush();

        $this->addFlash('success', 'Objectif de vie supprimé.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/milestone/{goalId}/add', name: 'admin_plan_de_vie_add_milestone', methods: ['POST'])]
    public function addMilestone(int $goalId, Request $request, LifeGoalRepository $goalRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_milestone_' . $goalId, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal = $goalRepo->find($goalId);

        if (!$goal || $goal->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Objectif de vie introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $title = $request->request->get('title');
        $dueDateStr = $request->request->get('dueDate');

        if (empty($title) || empty($dueDateStr)) {
            $this->addFlash('error', 'Veuillez remplir le titre et la date d\'échéance du jalon.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $milestone = new LifeMilestone();
        $milestone->setLifeGoal($goal);
        $milestone->setTitle($title);
        $milestone->setDueDate(new \DateTime($dueDateStr));
        $milestone->setCompleted(false);

        $em->persist($milestone);
        $em->flush();

        // Recalculate progress
        $this->recalculateGoalProgress($goal, $em);

        $this->addFlash('success', 'Jalon ajouté avec succès.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/milestone/toggle/{id}', name: 'admin_plan_de_vie_toggle_milestone', methods: ['POST'])]
    public function toggleMilestone(int $id, LifeMilestoneRepository $milestoneRepo, EntityManagerInterface $em): JsonResponse
    {
        $milestone = $milestoneRepo->find($id);

        if (!$milestone || $milestone->getLifeGoal()->getPlanDeVie()->getUser() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Jalon introuvable.'], 404);
        }

        $milestone->setCompleted(!$milestone->isCompleted());
        $em->flush();

        // Recalculate progress
        $goal = $milestone->getLifeGoal();
        $newProgress = $this->recalculateGoalProgress($goal, $em);

        return new JsonResponse([
            'success' => true,
            'completed' => $milestone->isCompleted(),
            'goalProgress' => $newProgress,
            'goalId' => $goal->getId(),
        ]);
    }

    #[Route('/milestone/{id}/delete', name: 'admin_plan_de_vie_delete_milestone', methods: ['POST'])]
    public function deleteMilestone(int $id, Request $request, LifeMilestoneRepository $milestoneRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_milestone_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $milestone = $milestoneRepo->find($id);

        if (!$milestone || $milestone->getLifeGoal()->getPlanDeVie()->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Jalon introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $goal = $milestone->getLifeGoal();
        $em->remove($milestone);
        $em->flush();

        // Recalculate progress
        $this->recalculateGoalProgress($goal, $em);

        $this->addFlash('success', 'Jalon supprimé.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    private function recalculateGoalProgress(LifeGoal $goal, EntityManagerInterface $em): int
    {
        $milestones = $goal->getMilestones();
        $total = count($milestones);

        if ($total > 0) {
            $completed = 0;
            foreach ($milestones as $m) {
                if ($m->isCompleted()) {
                    $completed++;
                }
            }
            $progress = (int) round(($completed / $total) * 100);
            $goal->setProgress($progress);
            $em->flush();
            return $progress;
        }

        return $goal->getProgress();
    }

    #[Route('/internal-balance/add', name: 'admin_plan_de_vie_add_internal_balance', methods: ['POST'])]
    public function addInternalBalance(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_internal_balance', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $type = $request->request->get('type');
        $subtype = $request->request->get('subtype');
        $text = $request->request->get('text');

        if (!in_array($type, ['CONVICTION', 'LIMITING_BELIEF'], true)) {
            $this->addFlash('error', 'Type invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        if (empty($text)) {
            $this->addFlash('error', 'Le texte est obligatoire.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $ib = new InternalBalance();
        $ib->setUser($this->getUser());
        $ib->setType($type);
        $ib->setSubtype($type === 'CONVICTION' ? $subtype : null);
        $ib->setText(trim($text));

        $em->persist($ib);
        $em->flush();

        $this->addFlash('success', 'Élément ajouté à la balance interne.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/internal-balance/{id}/edit', name: 'admin_plan_de_vie_edit_internal_balance', methods: ['POST'])]
    public function editInternalBalance(int $id, Request $request, InternalBalanceRepository $ibRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('edit_internal_balance_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $ib = $ibRepo->find($id);

        if (!$ib || $ib->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Élément introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $subtype = $request->request->get('subtype');
        $text = $request->request->get('text');

        if (empty($text)) {
            $this->addFlash('error', 'Le texte est obligatoire.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        if ($ib->getType() === 'CONVICTION') {
            $ib->setSubtype($subtype);
        }
        $ib->setText(trim($text));
        $em->flush();

        $this->addFlash('success', 'Élément mis à jour.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }

    #[Route('/internal-balance/{id}/delete', name: 'admin_plan_de_vie_delete_internal_balance', methods: ['POST'])]
    public function deleteInternalBalance(int $id, Request $request, InternalBalanceRepository $ibRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_internal_balance_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $ib = $ibRepo->find($id);

        if (!$ib || $ib->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Élément introuvable.');
            return $this->redirectToRoute('admin_plan_de_vie');
        }

        $em->remove($ib);
        $em->flush();

        $this->addFlash('success', 'Élément supprimé de la balance interne.');
        return $this->redirectToRoute('admin_plan_de_vie');
    }
}
