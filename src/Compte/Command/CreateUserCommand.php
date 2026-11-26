<?php

namespace App\Compte\Command;

use App\Compte\Entity\User;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeDeveloppement\Entity\PlanDeDeveloppementPersonnel;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\Abonnement\Entity\Subscription;
use App\Compte\Entity\UserProfile;
use App\Notification\Entity\NotificationPreference;
use App\Abonnement\Enum\SubscriptionStatus;
use App\Abonnement\Enum\SubscriptionTier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un nouvel utilisateur avec ses 5 plans associés.',
)]
class CreateUserCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email de l\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe de l\'utilisateur')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donner le rôle administrateur (ROLE_ADMIN)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim($input->getArgument('email')));
        $password = $input->getArgument('password');

        // Check if user already exists
        $userRepository = $this->entityManager->getRepository(User::class);
        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->error(sprintf('L\'utilisateur %s existe déjà.', $email));
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : []);
        // Compte créé par un administrateur : pas de vérification d'e-mail
        $user->setIsVerified(true);

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

        $io->success(sprintf('L\'utilisateur %s a été créé avec succès avec ses 5 plans pivots !', $email));

        return Command::SUCCESS;
    }
}
