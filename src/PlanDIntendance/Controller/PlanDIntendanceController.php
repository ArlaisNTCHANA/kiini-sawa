<?php

namespace App\PlanDIntendance\Controller;

use App\PlanDeVie\Controller\AbstractPlanController;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Entity\StewardshipRhythm;
use App\PlanDIntendance\Repository\StewardshipItemRepository;
use App\PlanDIntendance\Repository\StewardshipRhythmRepository;
use App\PlanDIntendance\Service\Stewardship;
use App\PlanDIntendance\Service\StewardshipInventory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Plan d'Intendance : « ce que je gère » (livre, partie VI, chapitre 18).
 *
 * Une page guidée qui suit le cycle de l'intendant : inventaire, état, décision, compte rendu.
 * Formulaires classiques (protection CSRF par l'origine, comme les autres plans) ; réservé à PRO
 * sauf si l'administrateur l'ouvre en gratuit (AppConfig::enableStewardshipFree).
 */
#[Route('/plan-d-intendance', name: 'app_plan_d_intendance')]
#[IsGranted('ROLE_USER')]
class PlanDIntendanceController extends AbstractPlanController
{
    public function __construct(
        private readonly StewardshipItemRepository $items,
        private readonly StewardshipRhythmRepository $rhythms,
        private readonly StewardshipInventory $inventory,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $this->requirePlan('INTENDANCE');
        $user = $this->user();

        return $this->render('plan_d_intendance/index.html.twig', $this->inventory->overview($user) + [
            'suggestions' => $this->inventory->suggestions($user),
            'rhythm' => $this->rhythms->findOneBy(['user' => $user]),
            'giftResults' => $this->testResults->gifts($user),
            'lifeGoals' => $user->getPlanDeVie()?->getLifeGoals()->toArray() ?? [],
        ]);
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        $this->requirePlan('INTENDANCE');
        $user = $this->user();

        return $this->render('plan_d_intendance/visualize.html.twig', $this->inventory->overview($user) + [
            'rhythm' => $this->rhythms->findOneBy(['user' => $user]),
            'name' => $user->getDisplayName(),
        ]);
    }

    #[Route('/element', name: '_add', methods: ['POST'])]
    public function add(Request $request): RedirectResponse
    {
        $this->requirePlan('INTENDANCE');
        if ($expired = $this->expired($request)) {
            return $expired;
        }
        $origin = $request->request->getString('origin');
        $label = trim($request->request->getString('label'));
        if (!Stewardship::isOrigin($origin) || '' === $label) {
            $this->addFlash('warning', 'Écrivez en quelques mots ce qui vous est confié.');

            return $this->back();
        }
        $item = (new StewardshipItem())->setUser($this->user())->setOrigin($origin)->setLabel($label)
            ->setPosition(\count($this->items->forUser($this->user())));
        $this->em->persist($item);
        $this->em->flush();

        return $this->back('element-'.$item->getId());
    }

    #[Route('/element/{id}', name: '_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): RedirectResponse
    {
        $item = $this->own($id);
        if ($expired = $this->expired($request)) {
            return $expired;
        }
        $form = $request->request;
        if ($form->has('label') && '' !== trim($form->getString('label'))) {
            $item->setLabel($form->getString('label'));
        }
        if ($form->has('status')) {
            $status = $form->getString('status');
            $item->setStatus(isset(Stewardship::STATUSES[$status]) ? $status : null);
        }
        if ($form->has('decision')) {
            $decision = $form->getString('decision');
            $item->setDecision(isset(Stewardship::DECISIONS[$decision]) ? $decision : null);
        }
        if ($form->has('action')) {
            $item->setActionText($form->getString('action'));
        }
        if ($form->has('life_goal')) {
            $goalId = $form->getInt('life_goal');
            $goal = null;
            foreach ($this->user()->getPlanDeVie()?->getLifeGoals() ?? [] as $g) {
                if ($g->getId() === $goalId) {
                    $goal = $g;
                }
            }
            $item->setLifeGoal($goal);
        }
        if ($form->has('note')) {
            $item->setNote($form->getString('note'));
        }
        if ($form->has('priority')) {
            $wanted = $form->getBoolean('priority');
            if ($wanted && !$item->isPriority() && $this->items->countPriorities($this->user()) >= Stewardship::MAX_PRIORITIES) {
                $this->addFlash('warning', 'Cinq priorités au plus : mieux vaut peu d\'éléments bien gérés. Retirez-en une avant d\'en ajouter une autre.');
            } else {
                $item->setPriority($wanted);
            }
        }
        $this->em->flush();

        return $this->back('element-'.$item->getId());
    }

    #[Route('/element/{id}/fait', name: '_done', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function done(int $id, Request $request): RedirectResponse
    {
        $item = $this->own($id);
        if ($expired = $this->expired($request)) {
            return $expired;
        }
        $item->setActionDoneAt($item->getActionDoneAt() ? null : new \DateTimeImmutable());
        $this->em->flush();

        return $this->back('priorites');
    }

    #[Route('/element/{id}/supprimer', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $item = $this->own($id);
        if ($expired = $this->expired($request)) {
            return $expired;
        }
        $origin = $item->getOrigin();
        $this->em->remove($item);
        $this->em->flush();

        return $this->back('origine-'.strtolower($origin));
    }

    #[Route('/rythme', name: '_rhythm', methods: ['POST'])]
    public function rhythm(Request $request): RedirectResponse
    {
        $this->requirePlan('INTENDANCE');
        if ($expired = $this->expired($request)) {
            return $expired;
        }
        $rhythm = $this->rhythmFor();
        $rhythm->setFrequency($request->request->getString('frequency'))->setWithWhom($request->request->getString('with_whom'));
        if ($request->request->getBoolean('review')) {
            $rhythm->setLastReviewAt(new \DateTimeImmutable());
            $this->addFlash('success', 'Compte rendu noté. « C\'est bien, bon et fidèle serviteur. »');
        }
        $this->em->flush();

        return $this->back('compte-rendu');
    }

    /** Jeton CSRF du formulaire (en plus du contrôle d'origine) : null s'il est valide, sinon retour à la page avec un message. */
    private function expired(Request $request): ?RedirectResponse
    {
        if ($this->isCsrfTokenValid('plan_intendance', $request->request->getString('_csrf_token'))) {
            return null;
        }
        $this->addFlash('error', 'La page a expiré. Vos modifications n\'ont pas été enregistrées : merci de réessayer.');

        return $this->back();
    }

    private function rhythmFor(): StewardshipRhythm
    {
        $rhythm = $this->rhythms->findOneBy(['user' => $this->user()]);
        if (!$rhythm) {
            $rhythm = (new StewardshipRhythm())->setUser($this->user());
            $this->em->persist($rhythm);
        }

        return $rhythm;
    }

    private function own(int $id): StewardshipItem
    {
        $this->requirePlan('INTENDANCE');
        $item = $this->items->find($id);
        if (!$item || $item->getUser()?->getId() !== $this->user()->getId()) {
            throw $this->createNotFoundException();
        }

        return $item;
    }

    private function back(?string $anchor = null): RedirectResponse
    {
        return new RedirectResponse($this->generateUrl('app_plan_d_intendance').($anchor ? '#'.$anchor : ''));
    }
}
