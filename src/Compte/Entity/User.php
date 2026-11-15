<?php

namespace App\Compte\Entity;

use App\Compte\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Abonnement\Entity\Subscription;
use App\Admin\Entity\TelemetryEvent;
use App\Bilan\Entity\CheckIn;
use App\Bilan\Entity\MonthlyReview;
use App\Coaching\Entity\AdviceRequest;
use App\Coaching\Entity\CoachingMessage;
use App\Coaching\Entity\GoalSuggestion;
use App\Defis\Entity\Challenge;
use App\Defis\Entity\ChallengeInvitation;
use App\Defis\Entity\ChallengeParticipant;
use App\Feedback\Entity\AppFeedback;
use App\Feedback\Entity\CommunityFeedback;
use App\Feedback\Entity\Feedback;
use App\Feedback\Entity\FeedbackRequest;
use App\Journal\Entity\JournalCollection;
use App\Journal\Entity\ReflectionNote;
use App\Listes\Entity\UserList;
use App\Notification\Entity\Notification;
use App\Notification\Entity\NotificationPreference;
use App\Personnalite\Entity\ProfileTest;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeCroissance\Entity\Principle;
use App\PlanDeDeveloppement\Entity\PlanDeDeveloppementPersonnel;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\PlanHistory;
use App\Planner\Entity\PlannerTask;
use App\Rituels\Entity\Habit;
use App\Social\Entity\Connection;
use App\Social\Entity\SharedLink;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isOnboardingCompleted = false;

    /** L'utilisateur a prouvé qu'il possède son adresse email (code à 6 chiffres). */
    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    /** Date d'acceptation des CGU et de la politique de confidentialité (preuve du consentement). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    /** Dernière activité dans l'application (mise à jour au plus une fois par heure). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActiveAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->habits = new ArrayCollection();
        $this->profileTests = new ArrayCollection();
        $this->connections = new ArrayCollection();
        $this->connectedTo = new ArrayCollection();
        $this->adviceRequests = new ArrayCollection();
        $this->coachingMessagesSent = new ArrayCollection();
        $this->coachingMessagesReceived = new ArrayCollection();
        $this->sentSuggestions = new ArrayCollection();
        $this->receivedSuggestions = new ArrayCollection();
        $this->journalCollections = new ArrayCollection();
        $this->reflectionNotes = new ArrayCollection();
        $this->plannerTasks = new ArrayCollection();
        $this->userLists = new ArrayCollection();
        $this->checkIns = new ArrayCollection();
        $this->monthlyReviews = new ArrayCollection();
        $this->planHistories = new ArrayCollection();
        $this->createdChallenges = new ArrayCollection();
        $this->challenges = new ArrayCollection();
        $this->sentChallengeInvitations = new ArrayCollection();
        $this->receivedChallengeInvitations = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->sentFeedbacks = new ArrayCollection();
        $this->receivedFeedbacks = new ArrayCollection();
        $this->feedbackRequests = new ArrayCollection();
        $this->communityFeedbacks = new ArrayCollection();
        $this->appFeedbacks = new ArrayCollection();
        $this->userSessions = new ArrayCollection();
        $this->telemetryEvents = new ArrayCollection();
        $this->principles = new ArrayCollection();
        $this->internalBalances = new ArrayCollection();
        $this->sharedLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        // Emails insensibles à la casse : toujours stockés en minuscules
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }


    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PlanDeVie $planDeVie = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PlanDeCroissance $planDeCroissance = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PlanDeDeveloppementPersonnel $planDeDeveloppementPersonnel = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PlanDHygiene $planDHygiene = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PlanDeTransmission $planDeTransmission = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Subscription $subscription = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserProfile $userProfile = null;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Habit::class, cascade: ['persist', 'remove'])]
    private Collection $habits;

    /**
     * @var Collection<int, ProfileTest>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ProfileTest::class, cascade: ['persist', 'remove'])]
    private Collection $profileTests;

    /**
     * @var Collection<int, Connection>
     */
    #[ORM\OneToMany(mappedBy: 'requester', targetEntity: Connection::class, cascade: ['persist', 'remove'])]
    private Collection $connections;

    /**
     * @var Collection<int, Connection>
     */
    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: Connection::class, cascade: ['persist', 'remove'])]
    private Collection $connectedTo;

    /**
     * @var Collection<int, AdviceRequest>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AdviceRequest::class, cascade: ['persist', 'remove'])]
    private Collection $adviceRequests;

    /**
     * @var Collection<int, CoachingMessage>
     */
    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: CoachingMessage::class, cascade: ['persist', 'remove'])]
    private Collection $coachingMessagesSent;

    /**
     * @var Collection<int, CoachingMessage>
     */
    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: CoachingMessage::class, cascade: ['persist', 'remove'])]
    private Collection $coachingMessagesReceived;

    /**
     * @var Collection<int, GoalSuggestion>
     */
    #[ORM\OneToMany(mappedBy: 'fromUser', targetEntity: GoalSuggestion::class, cascade: ['persist', 'remove'])]
    private Collection $sentSuggestions;

    /**
     * @var Collection<int, GoalSuggestion>
     */
    #[ORM\OneToMany(mappedBy: 'toUser', targetEntity: GoalSuggestion::class, cascade: ['persist', 'remove'])]
    private Collection $receivedSuggestions;

    /**
     * @var Collection<int, JournalCollection>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: JournalCollection::class, cascade: ['persist', 'remove'])]
    private Collection $journalCollections;

    /**
     * @var Collection<int, ReflectionNote>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ReflectionNote::class, cascade: ['persist', 'remove'])]
    private Collection $reflectionNotes;

    /**
     * @var Collection<int, PlannerTask>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PlannerTask::class, cascade: ['persist', 'remove'])]
    private Collection $plannerTasks;

    /**
     * @var Collection<int, UserList>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserList::class, cascade: ['persist', 'remove'])]
    private Collection $userLists;

    /**
     * @var Collection<int, CheckIn>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CheckIn::class, cascade: ['persist', 'remove'])]
    private Collection $checkIns;

    /**
     * @var Collection<int, MonthlyReview>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: MonthlyReview::class, cascade: ['persist', 'remove'])]
    private Collection $monthlyReviews;

    /**
     * @var Collection<int, PlanHistory>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PlanHistory::class, cascade: ['persist', 'remove'])]
    private Collection $planHistories;

    /**
     * @var Collection<int, Challenge>
     */
    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: Challenge::class, cascade: ['persist', 'remove'])]
    private Collection $createdChallenges;

    /**
     * @var Collection<int, ChallengeParticipant>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ChallengeParticipant::class, cascade: ['persist', 'remove'])]
    private Collection $challenges;

    /**
     * @var Collection<int, ChallengeInvitation>
     */
    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: ChallengeInvitation::class, cascade: ['persist', 'remove'])]
    private Collection $sentChallengeInvitations;

    /**
     * @var Collection<int, ChallengeInvitation>
     */
    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: ChallengeInvitation::class, cascade: ['persist', 'remove'])]
    private Collection $receivedChallengeInvitations;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, cascade: ['persist', 'remove'])]
    private Collection $notifications;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: NotificationPreference::class, cascade: ['persist', 'remove'])]
    private ?NotificationPreference $notificationPreference = null;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(mappedBy: 'fromUser', targetEntity: Feedback::class, cascade: ['persist', 'remove'])]
    private Collection $sentFeedbacks;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(mappedBy: 'toUser', targetEntity: Feedback::class, cascade: ['persist', 'remove'])]
    private Collection $receivedFeedbacks;

    /**
     * @var Collection<int, FeedbackRequest>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FeedbackRequest::class, cascade: ['persist', 'remove'])]
    private Collection $feedbackRequests;

    /**
     * @var Collection<int, CommunityFeedback>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CommunityFeedback::class, cascade: ['persist', 'remove'])]
    private Collection $communityFeedbacks;

    /**
     * @var Collection<int, AppFeedback>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AppFeedback::class, cascade: ['persist', 'remove'])]
    private Collection $appFeedbacks;

    /**
     * @var Collection<int, UserSession>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserSession::class, cascade: ['persist', 'remove'])]
    private Collection $userSessions;

    /**
     * @var Collection<int, TelemetryEvent>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TelemetryEvent::class, cascade: ['persist', 'remove'])]
    private Collection $telemetryEvents;

    /**
     * @var Collection<int, Principle>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Principle::class, cascade: ['persist', 'remove'])]
    private Collection $principles;

    /**
     * @var Collection<int, InternalBalance>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: InternalBalance::class, cascade: ['persist', 'remove'])]
    private Collection $internalBalances;

    /**
     * @var Collection<int, SharedLink>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: SharedLink::class, cascade: ['persist', 'remove'])]
    private Collection $sharedLinks;

    public function getPlanDeVie(): ?PlanDeVie
    {
        return $this->planDeVie;
    }

    public function setPlanDeVie(PlanDeVie $planDeVie): static
    {
        // set the owning side of the relation if necessary
        if ($planDeVie->getUser() !== $this) {
            $planDeVie->setUser($this);
        }

        $this->planDeVie = $planDeVie;

        return $this;
    }

    public function getPlanDeCroissance(): ?PlanDeCroissance
    {
        return $this->planDeCroissance;
    }

    public function setPlanDeCroissance(PlanDeCroissance $planDeCroissance): static
    {
        // set the owning side of the relation if necessary
        if ($planDeCroissance->getUser() !== $this) {
            $planDeCroissance->setUser($this);
        }

        $this->planDeCroissance = $planDeCroissance;

        return $this;
    }

    public function getPlanDeDeveloppementPersonnel(): ?PlanDeDeveloppementPersonnel
    {
        return $this->planDeDeveloppementPersonnel;
    }

    public function setPlanDeDeveloppementPersonnel(PlanDeDeveloppementPersonnel $planDeDeveloppementPersonnel): static
    {
        // set the owning side of the relation if necessary
        if ($planDeDeveloppementPersonnel->getUser() !== $this) {
            $planDeDeveloppementPersonnel->setUser($this);
        }

        $this->planDeDeveloppementPersonnel = $planDeDeveloppementPersonnel;

        return $this;
    }

    public function getPlanDHygiene(): ?PlanDHygiene
    {
        return $this->planDHygiene;
    }

    public function setPlanDHygiene(PlanDHygiene $planDHygiene): static
    {
        // set the owning side of the relation if necessary
        if ($planDHygiene->getUser() !== $this) {
            $planDHygiene->setUser($this);
        }

        $this->planDHygiene = $planDHygiene;

        return $this;
    }

    public function getPlanDeTransmission(): ?PlanDeTransmission
    {
        return $this->planDeTransmission;
    }

    public function setPlanDeTransmission(PlanDeTransmission $planDeTransmission): static
    {
        // set the owning side of the relation if necessary
        if ($planDeTransmission->getUser() !== $this) {
            $planDeTransmission->setUser($this);
        }

        $this->planDeTransmission = $planDeTransmission;

        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): static
    {
        // set the owning side of the relation if necessary
        if ($subscription->getUser() !== $this) {
            $subscription->setUser($this);
        }

        $this->subscription = $subscription;

        return $this;
    }

    public function getUserProfile(): ?UserProfile
    {
        return $this->userProfile;
    }

    public function setUserProfile(UserProfile $userProfile): static
    {
        // set the owning side of the relation if necessary
        if ($userProfile->getUser() !== $this) {
            $userProfile->setUser($this);
        }

        $this->userProfile = $userProfile;

        return $this;
    }

    /**
     * @return Collection<int, Habit>
     */
    public function getHabits(): Collection
    {
        return $this->habits;
    }

    public function addHabit(Habit $habit): static
    {
        if (!$this->habits->contains($habit)) {
            $this->habits->add($habit);
            $habit->setUser($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getUser() === $this) {
                $habit->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProfileTest>
     */
    public function getProfileTests(): Collection
    {
        return $this->profileTests;
    }

    public function addProfileTest(ProfileTest $profileTest): static
    {
        if (!$this->profileTests->contains($profileTest)) {
            $this->profileTests->add($profileTest);
            $profileTest->setUser($this);
        }

        return $this;
    }

    public function removeProfileTest(ProfileTest $profileTest): static
    {
        if ($this->profileTests->removeElement($profileTest)) {
            // set the owning side to null (unless already changed)
            if ($profileTest->getUser() === $this) {
                $profileTest->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Connection>
     */
    public function getConnections(): Collection
    {
        return $this->connections;
    }

    public function addConnection(Connection $connection): static
    {
        if (!$this->connections->contains($connection)) {
            $this->connections->add($connection);
            $connection->setRequester($this);
        }

        return $this;
    }

    public function removeConnection(Connection $connection): static
    {
        if ($this->connections->removeElement($connection)) {
            if ($connection->getRequester() === $this) {
                $connection->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Connection>
     */
    public function getConnectedTo(): Collection
    {
        return $this->connectedTo;
    }

    public function addConnectedTo(Connection $connection): static
    {
        if (!$this->connectedTo->contains($connection)) {
            $this->connectedTo->add($connection);
            $connection->setReceiver($this);
        }

        return $this;
    }

    public function removeConnectedTo(Connection $connection): static
    {
        if ($this->connectedTo->removeElement($connection)) {
            if ($connection->getReceiver() === $this) {
                $connection->setReceiver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdviceRequest>
     */
    public function getAdviceRequests(): Collection
    {
        return $this->adviceRequests;
    }

    public function addAdviceRequest(AdviceRequest $adviceRequest): static
    {
        if (!$this->adviceRequests->contains($adviceRequest)) {
            $this->adviceRequests->add($adviceRequest);
            $adviceRequest->setUser($this);
        }

        return $this;
    }

    public function removeAdviceRequest(AdviceRequest $adviceRequest): static
    {
        if ($this->adviceRequests->removeElement($adviceRequest)) {
            if ($adviceRequest->getUser() === $this) {
                $adviceRequest->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CoachingMessage>
     */
    public function getCoachingMessagesSent(): Collection
    {
        return $this->coachingMessagesSent;
    }

    public function addCoachingMessageSent(CoachingMessage $message): static
    {
        if (!$this->coachingMessagesSent->contains($message)) {
            $this->coachingMessagesSent->add($message);
            $message->setSender($this);
        }

        return $this;
    }

    public function removeCoachingMessageSent(CoachingMessage $message): static
    {
        if ($this->coachingMessagesSent->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CoachingMessage>
     */
    public function getCoachingMessagesReceived(): Collection
    {
        return $this->coachingMessagesReceived;
    }

    public function addCoachingMessageReceived(CoachingMessage $message): static
    {
        if (!$this->coachingMessagesReceived->contains($message)) {
            $this->coachingMessagesReceived->add($message);
            $message->setReceiver($this);
        }

        return $this;
    }

    public function removeCoachingMessageReceived(CoachingMessage $message): static
    {
        if ($this->coachingMessagesReceived->removeElement($message)) {
            if ($message->getReceiver() === $this) {
                $message->setReceiver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GoalSuggestion>
     */
    public function getSentSuggestions(): Collection
    {
        return $this->sentSuggestions;
    }

    public function addSentSuggestion(GoalSuggestion $suggestion): static
    {
        if (!$this->sentSuggestions->contains($suggestion)) {
            $this->sentSuggestions->add($suggestion);
            $suggestion->setFromUser($this);
        }

        return $this;
    }

    public function removeSentSuggestion(GoalSuggestion $suggestion): static
    {
        if ($this->sentSuggestions->removeElement($suggestion)) {
            if ($suggestion->getFromUser() === $this) {
                $suggestion->setFromUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GoalSuggestion>
     */
    public function getReceivedSuggestions(): Collection
    {
        return $this->receivedSuggestions;
    }

    public function addReceivedSuggestion(GoalSuggestion $suggestion): static
    {
        if (!$this->receivedSuggestions->contains($suggestion)) {
            $this->receivedSuggestions->add($suggestion);
            $suggestion->setToUser($this);
        }

        return $this;
    }

    public function removeReceivedSuggestion(GoalSuggestion $suggestion): static
    {
        if ($this->receivedSuggestions->removeElement($suggestion)) {
            if ($suggestion->getToUser() === $this) {
                $suggestion->setToUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, JournalCollection>
     */
    public function getJournalCollections(): Collection
    {
        return $this->journalCollections;
    }

    public function addJournalCollection(JournalCollection $journalCollection): static
    {
        if (!$this->journalCollections->contains($journalCollection)) {
            $this->journalCollections->add($journalCollection);
            $journalCollection->setUser($this);
        }

        return $this;
    }

    public function removeJournalCollection(JournalCollection $journalCollection): static
    {
        if ($this->journalCollections->removeElement($journalCollection)) {
            if ($journalCollection->getUser() === $this) {
                $journalCollection->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReflectionNote>
     */
    public function getReflectionNotes(): Collection
    {
        return $this->reflectionNotes;
    }

    public function addReflectionNote(ReflectionNote $reflectionNote): static
    {
        if (!$this->reflectionNotes->contains($reflectionNote)) {
            $this->reflectionNotes->add($reflectionNote);
            $reflectionNote->setUser($this);
        }

        return $this;
    }

    public function removeReflectionNote(ReflectionNote $reflectionNote): static
    {
        if ($this->reflectionNotes->removeElement($reflectionNote)) {
            if ($reflectionNote->getUser() === $this) {
                $reflectionNote->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlannerTask>
     */
    public function getPlannerTasks(): Collection
    {
        return $this->plannerTasks;
    }

    public function addPlannerTask(PlannerTask $plannerTask): static
    {
        if (!$this->plannerTasks->contains($plannerTask)) {
            $this->plannerTasks->add($plannerTask);
            $plannerTask->setUser($this);
        }

        return $this;
    }

    public function removePlannerTask(PlannerTask $plannerTask): static
    {
        if ($this->plannerTasks->removeElement($plannerTask)) {
            if ($plannerTask->getUser() === $this) {
                $plannerTask->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserList>
     */
    public function getUserLists(): Collection
    {
        return $this->userLists;
    }

    public function addUserList(UserList $userList): static
    {
        if (!$this->userLists->contains($userList)) {
            $this->userLists->add($userList);
            $userList->setUser($this);
        }

        return $this;
    }

    public function removeUserList(UserList $userList): static
    {
        if ($this->userLists->removeElement($userList)) {
            if ($userList->getUser() === $this) {
                $userList->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CheckIn>
     */
    public function getCheckIns(): Collection
    {
        return $this->checkIns;
    }

    public function addCheckIn(CheckIn $checkIn): static
    {
        if (!$this->checkIns->contains($checkIn)) {
            $this->checkIns->add($checkIn);
            $checkIn->setUser($this);
        }

        return $this;
    }

    public function removeCheckIn(CheckIn $checkIn): static
    {
        if ($this->checkIns->removeElement($checkIn)) {
            if ($checkIn->getUser() === $this) {
                $checkIn->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MonthlyReview>
     */
    public function getMonthlyReviews(): Collection
    {
        return $this->monthlyReviews;
    }

    public function addMonthlyReview(MonthlyReview $monthlyReview): static
    {
        if (!$this->monthlyReviews->contains($monthlyReview)) {
            $this->monthlyReviews->add($monthlyReview);
            $monthlyReview->setUser($this);
        }

        return $this;
    }

    public function removeMonthlyReview(MonthlyReview $monthlyReview): static
    {
        if ($this->monthlyReviews->removeElement($monthlyReview)) {
            if ($monthlyReview->getUser() === $this) {
                $monthlyReview->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanHistory>
     */
    public function getPlanHistories(): Collection
    {
        return $this->planHistories;
    }

    public function addPlanHistory(PlanHistory $planHistory): static
    {
        if (!$this->planHistories->contains($planHistory)) {
            $this->planHistories->add($planHistory);
            $planHistory->setUser($this);
        }

        return $this;
    }

    public function removePlanHistory(PlanHistory $planHistory): static
    {
        if ($this->planHistories->removeElement($planHistory)) {
            if ($planHistory->getUser() === $this) {
                $planHistory->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Challenge>
     */
    public function getCreatedChallenges(): Collection
    {
        return $this->createdChallenges;
    }

    public function addCreatedChallenge(Challenge $challenge): static
    {
        if (!$this->createdChallenges->contains($challenge)) {
            $this->createdChallenges->add($challenge);
            $challenge->setCreator($this);
        }

        return $this;
    }

    public function removeCreatedChallenge(Challenge $challenge): static
    {
        if ($this->createdChallenges->removeElement($challenge)) {
            if ($challenge->getCreator() === $this) {
                $challenge->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeParticipant>
     */
    public function getChallenges(): Collection
    {
        return $this->challenges;
    }

    public function addChallenge(ChallengeParticipant $participant): static
    {
        if (!$this->challenges->contains($participant)) {
            $this->challenges->add($participant);
            $participant->setUser($this);
        }

        return $this;
    }

    public function removeChallenge(ChallengeParticipant $participant): static
    {
        if ($this->challenges->removeElement($participant)) {
            if ($participant->getUser() === $this) {
                $participant->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeInvitation>
     */
    public function getSentChallengeInvitations(): Collection
    {
        return $this->sentChallengeInvitations;
    }

    public function addSentChallengeInvitation(ChallengeInvitation $invitation): static
    {
        if (!$this->sentChallengeInvitations->contains($invitation)) {
            $this->sentChallengeInvitations->add($invitation);
            $invitation->setSender($this);
        }

        return $this;
    }

    public function removeSentChallengeInvitation(ChallengeInvitation $invitation): static
    {
        if ($this->sentChallengeInvitations->removeElement($invitation)) {
            if ($invitation->getSender() === $this) {
                $invitation->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeInvitation>
     */
    public function getReceivedChallengeInvitations(): Collection
    {
        return $this->receivedChallengeInvitations;
    }

    public function addReceivedChallengeInvitation(ChallengeInvitation $invitation): static
    {
        if (!$this->receivedChallengeInvitations->contains($invitation)) {
            $this->receivedChallengeInvitations->add($invitation);
            $invitation->setReceiver($this);
        }

        return $this;
    }

    public function removeReceivedChallengeInvitation(ChallengeInvitation $invitation): static
    {
        if ($this->receivedChallengeInvitations->removeElement($invitation)) {
            if ($invitation->getReceiver() === $this) {
                $invitation->setReceiver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getNotificationPreference(): ?NotificationPreference
    {
        return $this->notificationPreference;
    }

    public function setNotificationPreference(NotificationPreference $notificationPreference): static
    {
        // set the owning side of the relation if necessary
        if ($notificationPreference->getUser() !== $this) {
            $notificationPreference->setUser($this);
        }

        $this->notificationPreference = $notificationPreference;

        return $this;
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function getSentFeedbacks(): Collection
    {
        return $this->sentFeedbacks;
    }

    public function addSentFeedback(Feedback $feedback): static
    {
        if (!$this->sentFeedbacks->contains($feedback)) {
            $this->sentFeedbacks->add($feedback);
            $feedback->setFromUser($this);
        }

        return $this;
    }

    public function removeSentFeedback(Feedback $feedback): static
    {
        if ($this->sentFeedbacks->removeElement($feedback)) {
            if ($feedback->getFromUser() === $this) {
                $feedback->setFromUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function getReceivedFeedbacks(): Collection
    {
        return $this->receivedFeedbacks;
    }

    public function addReceivedFeedback(Feedback $feedback): static
    {
        if (!$this->receivedFeedbacks->contains($feedback)) {
            $this->receivedFeedbacks->add($feedback);
            $feedback->setToUser($this);
        }

        return $this;
    }

    public function removeReceivedFeedback(Feedback $feedback): static
    {
        if ($this->receivedFeedbacks->removeElement($feedback)) {
            if ($feedback->getToUser() === $this) {
                $feedback->setToUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FeedbackRequest>
     */
    public function getFeedbackRequests(): Collection
    {
        return $this->feedbackRequests;
    }

    public function addFeedbackRequest(FeedbackRequest $feedbackRequest): static
    {
        if (!$this->feedbackRequests->contains($feedbackRequest)) {
            $this->feedbackRequests->add($feedbackRequest);
            $feedbackRequest->setUser($this);
        }

        return $this;
    }

    public function removeFeedbackRequest(FeedbackRequest $feedbackRequest): static
    {
        if ($this->feedbackRequests->removeElement($feedbackRequest)) {
            if ($feedbackRequest->getUser() === $this) {
                $feedbackRequest->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CommunityFeedback>
     */
    public function getCommunityFeedbacks(): Collection
    {
        return $this->communityFeedbacks;
    }

    public function addCommunityFeedback(CommunityFeedback $communityFeedback): static
    {
        if (!$this->communityFeedbacks->contains($communityFeedback)) {
            $this->communityFeedbacks->add($communityFeedback);
            $communityFeedback->setUser($this);
        }

        return $this;
    }

    public function removeCommunityFeedback(CommunityFeedback $communityFeedback): static
    {
        if ($this->communityFeedbacks->removeElement($communityFeedback)) {
            if ($communityFeedback->getUser() === $this) {
                $communityFeedback->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AppFeedback>
     */
    public function getAppFeedbacks(): Collection
    {
        return $this->appFeedbacks;
    }

    public function addAppFeedback(AppFeedback $appFeedback): static
    {
        if (!$this->appFeedbacks->contains($appFeedback)) {
            $this->appFeedbacks->add($appFeedback);
            $appFeedback->setUser($this);
        }

        return $this;
    }

    public function removeAppFeedback(AppFeedback $appFeedback): static
    {
        if ($this->appFeedbacks->removeElement($appFeedback)) {
            if ($appFeedback->getUser() === $this) {
                $appFeedback->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function getUserSessions(): Collection
    {
        return $this->userSessions;
    }

    public function addUserSession(UserSession $userSession): static
    {
        if (!$this->userSessions->contains($userSession)) {
            $this->userSessions->add($userSession);
            $userSession->setUser($this);
        }

        return $this;
    }

    public function removeUserSession(UserSession $userSession): static
    {
        if ($this->userSessions->removeElement($userSession)) {
            if ($userSession->getUser() === $this) {
                $userSession->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TelemetryEvent>
     */
    public function getTelemetryEvents(): Collection
    {
        return $this->telemetryEvents;
    }

    public function addTelemetryEvent(TelemetryEvent $telemetryEvent): static
    {
        if (!$this->telemetryEvents->contains($telemetryEvent)) {
            $this->telemetryEvents->add($telemetryEvent);
            $telemetryEvent->setUser($this);
        }

        return $this;
    }

    public function removeTelemetryEvent(TelemetryEvent $telemetryEvent): static
    {
        if ($this->telemetryEvents->removeElement($telemetryEvent)) {
            if ($telemetryEvent->getUser() === $this) {
                $telemetryEvent->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Principle>
     */
    public function getPrinciples(): Collection
    {
        return $this->principles;
    }

    public function addPrinciple(Principle $principle): static
    {
        if (!$this->principles->contains($principle)) {
            $this->principles->add($principle);
            $principle->setUser($this);
        }

        return $this;
    }

    public function removePrinciple(Principle $principle): static
    {
        if ($this->principles->removeElement($principle)) {
            if ($principle->getUser() === $this) {
                $principle->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InternalBalance>
     */
    public function getInternalBalances(): Collection
    {
        return $this->internalBalances;
    }

    public function addInternalBalance(InternalBalance $internalBalance): static
    {
        if (!$this->internalBalances->contains($internalBalance)) {
            $this->internalBalances->add($internalBalance);
            $internalBalance->setUser($this);
        }

        return $this;
    }

    public function removeInternalBalance(InternalBalance $internalBalance): static
    {
        if ($this->internalBalances->removeElement($internalBalance)) {
            if ($internalBalance->getUser() === $this) {
                $internalBalance->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SharedLink>
     */
    public function getSharedLinks(): Collection
    {
        return $this->sharedLinks;
    }

    public function addSharedLink(SharedLink $sharedLink): static
    {
        if (!$this->sharedLinks->contains($sharedLink)) {
            $this->sharedLinks->add($sharedLink);
            $sharedLink->setUser($this);
        }

        return $this;
    }

    public function removeSharedLink(SharedLink $sharedLink): static
    {
        if ($this->sharedLinks->removeElement($sharedLink)) {
            if ($sharedLink->getUser() === $this) {
                $sharedLink->setUser(null);
            }
        }

        return $this;
    }

    public function isOnboardingCompleted(): ?bool
    {
        return $this->isOnboardingCompleted;
    }

    public function setIsOnboardingCompleted(bool $isOnboardingCompleted): static
    {
        $this->isOnboardingCompleted = $isOnboardingCompleted;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTimeImmutable $termsAcceptedAt): static
    {
        $this->termsAcceptedAt = $termsAcceptedAt;

        return $this;
    }

    public function getLastActiveAt(): ?\DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function setLastActiveAt(?\DateTimeImmutable $lastActiveAt): static
    {
        $this->lastActiveAt = $lastActiveAt;

        return $this;
    }

    /** Prénom affiché, ou la partie avant @ de l'email. */
    public function getDisplayName(): string
    {
        $firstName = $this->getUserProfile()?->getFirstName();

        return $firstName ?: explode('@', (string) $this->email)[0];
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        
        return $data;
    }
}

