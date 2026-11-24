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
    private bool $enableCoherenceAnalysisFree = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableAdvancedCommentsFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableChallengesFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableDiscoveryFree = true;

    /** Tests de personnalité accessibles gratuitement (identifiants séparés par des virgules ; null = valeurs par défaut ; vide = aucun). */
    public const DEFAULT_FREE_TESTS = ['disc', 'dons-spirituels'];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $freeTests = null;

    /** Messages « Saviez-vous que… » désactivés (codes de TipCatalog séparés par des virgules ; null ou vide = tous actifs). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $disabledTips = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableJournalFree = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableGrowthFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableHygieneFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableStewardshipFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableTransmissionFree = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enablePlannerFree = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableHabitsFree = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableSocialFree = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableCoachingFree = false;

    /** Texte intégral du livre pour les comptes gratuits (sinon : l'essentiel de chaque chapitre). */
    #[ORM\Column(options: ['default' => false])]
    private bool $enableMethodFree = false;

    // ── Offres et paiements (phase 5) ──────────────────────────────
    /** Prix en francs CFA (XAF), pour l'Afrique francophone (parité stricte avec l'euro). */
    #[ORM\Column(options: ['default' => 6500])]
    private int $proMonthlyPriceXaf = 6500;

    #[ORM\Column(options: ['default' => 52500])]
    private int $proYearlyPriceXaf = 52500;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableDevelopmentFree = false;

    /** Abonnement Duo (couple) : un paiement annuel pour les deux conjoints. */
    #[ORM\Column(options: ['default' => 119.99])]
    private float $duoYearlyPriceEur = 119.99;

    #[ORM\Column(options: ['default' => 78500])]
    private int $duoYearlyPriceXaf = 78500;

    /** Prix annuel d'une place dans l'offre Organisation (avant remise de volume). */
    #[ORM\Column(options: ['default' => 30])]
    private float $orgSeatYearlyPriceEur = 30;

    #[ORM\Column(options: ['default' => 20000])]
    private int $orgSeatYearlyPriceXaf = 20000;

    /** Coordonnées et consignes affichées au client pour un virement (IBAN, RIB, libellé à indiquer…). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bankTransferInstructions = null;

    /** Numéros et consignes pour un paiement Mobile Money (Orange Money, MTN MoMo…). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mobileMoneyInstructions = null;

    /** Adresse qui reçoit les alertes de paiement à valider (sinon : les administrateurs). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $paymentNotificationEmail = null;

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

    /** @return list<string> */
    /** @return list<string> */
    public function getDisabledTips(): array
    {
        return array_values(array_filter(explode(',', (string) $this->disabledTips)));
    }

    /** @param list<string> $codes */
    public function setDisabledTips(array $codes): static
    {
        $this->disabledTips = [] === $codes ? null : implode(',', $codes);

        return $this;
    }

    /** @return list<string> messages actifs (cases cochées dans l'administration) */
    public function getEnabledTips(): array
    {
        return array_values(array_diff(array_keys(\App\Notification\Service\TipCatalog::TIPS), $this->getDisabledTips()));
    }

    /** @param list<string> $codes */
    public function setEnabledTips(array $codes): static
    {
        return $this->setDisabledTips(array_values(array_diff(array_keys(\App\Notification\Service\TipCatalog::TIPS), $codes)));
    }

    public function getFreeTests(): array
    {
        return null === $this->freeTests ? self::DEFAULT_FREE_TESTS : array_values(array_filter(explode(',', $this->freeTests)));
    }

    /** @param list<string> $slugs */
    public function setFreeTests(array $slugs): static
    {
        $this->freeTests = implode(',', $slugs);

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

    public function isEnableStewardshipFree(): bool
    {
        return $this->enableStewardshipFree;
    }

    public function setEnableStewardshipFree(bool $enableStewardshipFree): static
    {
        $this->enableStewardshipFree = $enableStewardshipFree;

        return $this;
    }

    public function isEnableTransmissionFree(): bool
    {
        return $this->enableTransmissionFree;
    }

    public function setEnableTransmissionFree(bool $enableTransmissionFree): static
    {
        $this->enableTransmissionFree = $enableTransmissionFree;

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

    public function getProMonthlyPriceXaf(): int { return $this->proMonthlyPriceXaf; }
    public function setProMonthlyPriceXaf(int $v): static { $this->proMonthlyPriceXaf = $v; return $this; }
    public function getProYearlyPriceXaf(): int { return $this->proYearlyPriceXaf; }
    public function setProYearlyPriceXaf(int $v): static { $this->proYearlyPriceXaf = $v; return $this; }
    public function isEnableDevelopmentFree(): bool { return $this->enableDevelopmentFree; }
    public function setEnableDevelopmentFree(bool $v): static { $this->enableDevelopmentFree = $v; return $this; }
    public function getBankTransferInstructions(): ?string { return $this->bankTransferInstructions; }
    public function setBankTransferInstructions(?string $v): static { $this->bankTransferInstructions = $v; return $this; }
    public function getMobileMoneyInstructions(): ?string { return $this->mobileMoneyInstructions; }
    public function setMobileMoneyInstructions(?string $v): static { $this->mobileMoneyInstructions = $v; return $this; }
    public function getPaymentNotificationEmail(): ?string { return $this->paymentNotificationEmail; }
    public function setPaymentNotificationEmail(?string $v): static { $this->paymentNotificationEmail = $v; return $this; }
    public function getOrgSeatYearlyPriceEur(): float { return $this->orgSeatYearlyPriceEur; }
    public function setOrgSeatYearlyPriceEur(float $v): static { $this->orgSeatYearlyPriceEur = $v; return $this; }
    public function getOrgSeatYearlyPriceXaf(): int { return $this->orgSeatYearlyPriceXaf; }
    public function setOrgSeatYearlyPriceXaf(int $v): static { $this->orgSeatYearlyPriceXaf = $v; return $this; }
    public function getDuoYearlyPriceEur(): float { return $this->duoYearlyPriceEur; }
    public function setDuoYearlyPriceEur(float $v): static { $this->duoYearlyPriceEur = $v; return $this; }
    public function getDuoYearlyPriceXaf(): int { return $this->duoYearlyPriceXaf; }
    public function setDuoYearlyPriceXaf(int $v): static { $this->duoYearlyPriceXaf = $v; return $this; }
}
