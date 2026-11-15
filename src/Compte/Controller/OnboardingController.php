<?php

namespace App\Compte\Controller;

use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Value;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Onboarding court (environ 3 minutes) : prénom, raison d'être, 3 valeurs, un premier objectif.
 *
 * Ces réponses créent un premier Plan de Vie minimal ; l'utilisateur arrive ensuite
 * directement sur l'analyse de cohérence, qui lui montre quoi compléter
 * (voir docs/plan-v1.md, §3.3 « Parcours cible »).
 */
class OnboardingController extends AbstractController
{
    public const GOAL_CATEGORIES = [
        'BE' => 'Être',
        'DO' => 'Faire',
        'HAVE' => 'Avoir',
        'GIVE' => 'Donner',
    ];

    public const VALUE_SUGGESTIONS = ['Foi', 'Famille', 'Intégrité', 'Excellence', 'Service', 'Générosité', 'Humilité', 'Courage', 'Sagesse', 'Liberté', 'Fidélité', 'Joie'];

    #[Route('/onboarding', name: 'app_onboarding', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $data = [
            'firstName' => $user->getUserProfile()?->getFirstName() ?? '',
            'purpose' => '',
            'values' => ['', '', ''],
            'goalTitle' => '',
            'goalCategory' => 'DO',
            'goalDeadline' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'),
        ];

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('onboarding', $payload->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_onboarding');
            }

            // « Plus tard » : on n'impose rien, l'utilisateur pourra lancer le wizard du Plan de Vie
            if ($payload->getBoolean('skip')) {
                $user->setIsOnboardingCompleted(true);
                $em->flush();

                return $this->redirectToRoute('app_dashboard');
            }

            $data = [
                'firstName' => trim($payload->getString('firstName')),
                'purpose' => trim($payload->getString('purpose')),
                'values' => array_slice(array_pad(array_map(fn ($v) => trim((string) $v), $request->request->all('values')), 3, ''), 0, 3),
                'goalTitle' => trim($payload->getString('goalTitle')),
                'goalCategory' => $payload->getString('goalCategory'),
                'goalDeadline' => $payload->getString('goalDeadline'),
            ];
            $errors = $this->validate($data);

            if ([] === $errors) {
                $this->save($user, $data, $em);
                $this->addFlash('success', 'Votre premier Plan de Vie est posé. Voici son analyse de cohérence : chaque point vous indique comment l\'enrichir.');

                return $this->redirectToRoute('app_plan_de_vie', ['onglet' => 'analysis']);
            }
        }

        return $this->render('compte/onboarding.html.twig', [
            'data' => $data,
            'errors' => $errors,
            'categories' => self::GOAL_CATEGORIES,
            'suggestions' => self::VALUE_SUGGESTIONS,
        ], new Response(status: [] === $errors ? 200 : 422));
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ('' === $data['firstName'] || mb_strlen($data['firstName']) > 100) {
            $errors['firstName'] = 'Indiquez votre prénom.';
        }
        if (mb_strlen($data['purpose']) < 10 || mb_strlen($data['purpose']) > 1000) {
            $errors['purpose'] = 'Écrivez au moins une phrase courte (10 caractères).';
        }
        $values = array_filter($data['values']);
        if (\count($values) < 3 || \count(array_unique(array_map('mb_strtolower', $values))) < 3 || max(array_map('mb_strlen', $values)) > 255) {
            $errors['values'] = 'Choisissez 3 valeurs différentes.';
        }
        if (mb_strlen($data['goalTitle']) < 3 || mb_strlen($data['goalTitle']) > 255) {
            $errors['goalTitle'] = 'Décrivez votre objectif en quelques mots.';
        }
        if (!isset(self::GOAL_CATEGORIES[$data['goalCategory']])) {
            $errors['goalTitle'] = 'Choisissez le type d\'objectif.';
        }
        $deadline = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['goalDeadline']);
        if (!$deadline || $deadline <= new \DateTimeImmutable('today')) {
            $errors['goalDeadline'] = 'Choisissez une date dans le futur.';
        }

        return $errors;
    }

    private function save(User $user, array $data, EntityManagerInterface $em): void
    {
        $profile = $user->getUserProfile();
        if (null === $profile) {
            $profile = new UserProfile();
            $user->setUserProfile($profile);
            $em->persist($profile);
        }
        $profile->setFirstName($data['firstName']);

        $plan = $user->getPlanDeVie();
        if (null === $plan) {
            $plan = (new PlanDeVie())->setUser($user);
            $em->persist($plan);
        }

        // On complète sans jamais écraser ce que l'utilisateur aurait déjà saisi dans le wizard
        if (null === $plan->getPurpose()) {
            $em->persist((new Purpose())->setStatement($data['purpose'])->setPlanDeVie($plan));
        }
        if ($plan->getValues()->isEmpty()) {
            foreach ($data['values'] as $name) {
                $em->persist((new Value())->setName($name)->setBehavior('')->setPlanDeVie($plan));
            }
        }
        $em->persist((new LifeGoal())
            ->setTitle($data['goalTitle'])
            ->setCategory($data['goalCategory'])
            ->setDeadline(new \DateTime($data['goalDeadline']))
            ->setPlanDeVie($plan));

        $user->setIsOnboardingCompleted(true);
        $em->flush();
    }
}
