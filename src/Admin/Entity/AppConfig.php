<?php

namespace App\Admin\Entity;

use App\Admin\Repository\AppConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppConfigRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AppConfig
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $id = 'default';

    #[ORM\Column]
    private float $proMonthlyPrice = 9.99;

    #[ORM\Column]
    private float $proYearlyPrice = 79.99;

    #[ORM\Column(options: ['default' => 14])]
    private int $trialDurationDays = 14;

    #[ORM\Column(options: ['default' => 2])]
    private int $maxGrowthGoalsFree = 2;

    #[ORM\Column(options: ['default' => 3])]
    private int $maxHabitsFree = 3;

    #[ORM\Column(options: ['default' => 1])]
    private int $maxConnectionsFree = 1;

    #[ORM\Column(options: ['default' => 10])]
    private int $maxTasksPerDayFree = 10;

    #[ORM\Column(options: ['default' => 3])]
    private int $maxValuesFree = 3;

    #[ORM\Column(options: ['default' => 5])]
    private int $maxGoalsFree = 5;

    #[ORM\Column(options: ['default' => 1])]
    private int $maxChallengesFree = 1;

    #[ORM\Column(options: ['default' => false])]
    private bool $enablePrintFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableMapFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableAnalyticsFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableMonthlyReviewsFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enablePlanHistoryFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableMonthYearViewFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableAdvancedExportFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableHygieneDetailedFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableCoherenceAnalysisFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableAdvancedCommentsFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableChallengesFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableDiscoveryFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableJournalFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableGrowthFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableHygieneFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableContributionFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enablePlannerFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableHabitsFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableSocialFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableCoachingFree = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $enableMethodFree = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $defaultPurposeStatement = 'En attendant de discerner plus clairement le but de Dieu pour ma vie, je choisis de faire fructifier fidèlement les capacités qu’Il m’a déjà confiées afin d’apporter soutien, valeur et impact positif aux personnes placées sur mon chemin';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resendFromEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resendFromName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resendApiKey = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getProMonthlyPrice(): float
    {
        return $this->proMonthlyPrice;
    }

    public function setProMonthlyPrice(float $proMonthlyPrice): static
    {
        $this->proMonthlyPrice = $proMonthlyPrice;

        return $this;
    }

    public function getProYearlyPrice(): float
    {
        return $this->proYearlyPrice;
    }

    public function setProYearlyPrice(float $proYearlyPrice): static
    {
        $this->proYearlyPrice = $proYearlyPrice;

        return $this;
    }

    public function getTrialDurationDays(): int
    {
        return $this->trialDurationDays;
    }

    public function setTrialDurationDays(int $trialDurationDays): static
    {
        $this->trialDurationDays = $trialDurationDays;

        return $this;
    }

    public function getMaxGrowthGoalsFree(): int
    {
        return $this->maxGrowthGoalsFree;
    }

    public function setMaxGrowthGoalsFree(int $maxGrowthGoalsFree): static
    {
        $this->maxGrowthGoalsFree = $maxGrowthGoalsFree;

        return $this;
    }

    public function getMaxHabitsFree(): int
    {
        return $this->maxHabitsFree;
    }

    public function setMaxHabitsFree(int $maxHabitsFree): static
    {
        $this->maxHabitsFree = $maxHabitsFree;

        return $this;
    }

    public function getMaxConnectionsFree(): int
    {
        return $this->maxConnectionsFree;
    }

    public function setMaxConnectionsFree(int $maxConnectionsFree): static
    {
        $this->maxConnectionsFree = $maxConnectionsFree;

        return $this;
    }

    public function getMaxTasksPerDayFree(): int
    {
        return $this->maxTasksPerDayFree;
    }

    public function setMaxTasksPerDayFree(int $maxTasksPerDayFree): static
    {
        $this->maxTasksPerDayFree = $maxTasksPerDayFree;

        return $this;
    }

    public function getMaxValuesFree(): int
    {
        return $this->maxValuesFree;
    }

    public function setMaxValuesFree(int $maxValuesFree): static
    {
        $this->maxValuesFree = $maxValuesFree;

        return $this;
    }

    public function getMaxGoalsFree(): int
    {
        return $this->maxGoalsFree;
    }

    public function setMaxGoalsFree(int $maxGoalsFree): static
    {
        $this->maxGoalsFree = $maxGoalsFree;

        return $this;
    }

    public function getMaxChallengesFree(): int
    {
        return $this->maxChallengesFree;
    }

    public function setMaxChallengesFree(int $maxChallengesFree): static
    {
        $this->maxChallengesFree = $maxChallengesFree;

        return $this;
    }

    public function isEnablePrintFree(): bool
    {
        return $this->enablePrintFree;
    }

    public function setEnablePrintFree(bool $enablePrintFree): static
    {
        $this->enablePrintFree = $enablePrintFree;

        return $this;
    }

    public function isEnableMapFree(): bool
    {
        return $this->enableMapFree;
    }

    public function setEnableMapFree(bool $enableMapFree): static
    {
        $this->enableMapFree = $enableMapFree;

        return $this;
    }

    public function isEnableAnalyticsFree(): bool
    {
        return $this->enableAnalyticsFree;
    }

    public function setEnableAnalyticsFree(bool $enableAnalyticsFree): static
    {
        $this->enableAnalyticsFree = $enableAnalyticsFree;

        return $this;
    }

    public function isEnableMonthlyReviewsFree(): bool
    {
        return $this->enableMonthlyReviewsFree;
    }

    public function setEnableMonthlyReviewsFree(bool $enableMonthlyReviewsFree): static
    {
        $this->enableMonthlyReviewsFree = $enableMonthlyReviewsFree;

        return $this;
    }

    public function isEnablePlanHistoryFree(): bool
    {
        return $this->enablePlanHistoryFree;
    }

    public function setEnablePlanHistoryFree(bool $enablePlanHistoryFree): static
    {
        $this->enablePlanHistoryFree = $enablePlanHistoryFree;

        return $this;
    }

    public function isEnableMonthYearViewFree(): bool
    {
        return $this->enableMonthYearViewFree;
    }

    public function setEnableMonthYearViewFree(bool $enableMonthYearViewFree): static
    {
        $this->enableMonthYearViewFree = $enableMonthYearViewFree;

        return $this;
    }

    public function isEnableAdvancedExportFree(): bool
    {
        return $this->enableAdvancedExportFree;
    }

    public function setEnableAdvancedExportFree(bool $enableAdvancedExportFree): static
    {
        $this->enableAdvancedExportFree = $enableAdvancedExportFree;

        return $this;
    }

    public function isEnableHygieneDetailedFree(): bool
    {
        return $this->enableHygieneDetailedFree;
    }

    public function setEnableHygieneDetailedFree(bool $enableHygieneDetailedFree): static
    {
        $this->enableHygieneDetailedFree = $enableHygieneDetailedFree;

        return $this;
    }

    public function isEnableCoherenceAnalysisFree(): bool
    {
        return $this->enableCoherenceAnalysisFree;
    }

    public function setEnableCoherenceAnalysisFree(bool $enableCoherenceAnalysisFree): static
    {
        $this->enableCoherenceAnalysisFree = $enableCoherenceAnalysisFree;

        return $this;
    }

    public function isEnableAdvancedCommentsFree(): bool
    {
        return $this->enableAdvancedCommentsFree;
    }

    public function setEnableAdvancedCommentsFree(bool $enableAdvancedCommentsFree): static
    {
        $this->enableAdvancedCommentsFree = $enableAdvancedCommentsFree;

        return $this;
    }

    public function isEnableChallengesFree(): bool
    {
        return $this->enableChallengesFree;
    }

    public function setEnableChallengesFree(bool $enableChallengesFree): static
    {
        $this->enableChallengesFree = $enableChallengesFree;

        return $this;
    }

    public function isEnableDiscoveryFree(): bool
    {
        return $this->enableDiscoveryFree;
    }

    public function setEnableDiscoveryFree(bool $enableDiscoveryFree): static
    {
        $this->enableDiscoveryFree = $enableDiscoveryFree;

        return $this;
    }

    public function isEnableJournalFree(): bool
    {
        return $this->enableJournalFree;
    }

    public function setEnableJournalFree(bool $enableJournalFree): static
    {
        $this->enableJournalFree = $enableJournalFree;

        return $this;
    }

    public function isEnableGrowthFree(): bool
    {
        return $this->enableGrowthFree;
    }

    public function setEnableGrowthFree(bool $enableGrowthFree): static
    {
        $this->enableGrowthFree = $enableGrowthFree;

        return $this;
    }

    public function isEnableHygieneFree(): bool
    {
        return $this->enableHygieneFree;
    }

    public function setEnableHygieneFree(bool $enableHygieneFree): static
    {
        $this->enableHygieneFree = $enableHygieneFree;

        return $this;
    }

    public function isEnableContributionFree(): bool
    {
        return $this->enableContributionFree;
    }

    public function setEnableContributionFree(bool $enableContributionFree): static
    {
        $this->enableContributionFree = $enableContributionFree;

        return $this;
    }

    public function isEnablePlannerFree(): bool
    {
        return $this->enablePlannerFree;
    }

    public function setEnablePlannerFree(bool $enablePlannerFree): static
    {
        $this->enablePlannerFree = $enablePlannerFree;

        return $this;
    }

    public function isEnableHabitsFree(): bool
    {
        return $this->enableHabitsFree;
    }

    public function setEnableHabitsFree(bool $enableHabitsFree): static
    {
        $this->enableHabitsFree = $enableHabitsFree;

        return $this;
    }

    public function isEnableSocialFree(): bool
    {
        return $this->enableSocialFree;
    }

    public function setEnableSocialFree(bool $enableSocialFree): static
    {
        $this->enableSocialFree = $enableSocialFree;

        return $this;
    }

    public function isEnableCoachingFree(): bool
    {
        return $this->enableCoachingFree;
    }

    public function setEnableCoachingFree(bool $enableCoachingFree): static
    {
        $this->enableCoachingFree = $enableCoachingFree;

        return $this;
    }

    public function isEnableMethodFree(): bool
    {
        return $this->enableMethodFree;
    }

    public function setEnableMethodFree(bool $enableMethodFree): static
    {
        $this->enableMethodFree = $enableMethodFree;

        return $this;
    }

    public function getDefaultPurposeStatement(): ?string
    {
        return $this->defaultPurposeStatement ?? 'En attendant de discerner plus clairement le but de Dieu pour ma vie, je choisis de faire fructifier fidèlement les capacités qu’Il m’a déjà confiées afin d’apporter soutien, valeur et impact positif aux personnes placées sur mon chemin';
    }

    public function setDefaultPurposeStatement(?string $defaultPurposeStatement): static
    {
        $this->defaultPurposeStatement = $defaultPurposeStatement;

        return $this;
    }

    public function getResendFromEmail(): ?string
    {
        return $this->resendFromEmail;
    }

    public function setResendFromEmail(?string $resendFromEmail): static
    {
        $this->resendFromEmail = $resendFromEmail;

        return $this;
    }

    public function getResendFromName(): ?string
    {
        return $this->resendFromName;
    }

    public function setResendFromName(?string $resendFromName): static
    {
        $this->resendFromName = $resendFromName;

        return $this;
    }

    public function getResendApiKey(): ?string
    {
        return $this->resendApiKey;
    }

    public function setResendApiKey(?string $resendApiKey): static
    {
        $this->resendApiKey = $resendApiKey;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
