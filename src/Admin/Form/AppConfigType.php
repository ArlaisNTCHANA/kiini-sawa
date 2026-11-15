<?php

namespace App\Admin\Form;

use App\Admin\Entity\AppConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('defaultPurposeStatement', TextareaType::class, [
                'label' => 'But de Dieu par défaut',
                'help' => 'Texte proposé par défaut aux utilisateurs n\'ayant pas encore défini leur raison d\'être.',
                'required' => true,
                'attr' => ['rows' => 4, 'placeholder' => 'En attendant de discerner...'],
            ])
            ->add('proMonthlyPrice', NumberType::class, [
                'label' => 'Prix mensuel PRO (€)',
                'scale' => 2,
                'required' => true,
            ])
            ->add('proYearlyPrice', NumberType::class, [
                'label' => 'Prix annuel PRO (€)',
                'scale' => 2,
                'required' => true,
            ])
            ->add('trialDurationDays', IntegerType::class, [
                'label' => 'Durée de la période d\'essai (jours)',
                'help' => 'Essai PRO offert à l\'inscription. 0 pour désactiver.',
                'required' => true,
            ])
            ->add('proMonthlyPriceXaf', IntegerType::class, ['label' => 'Prix mensuel PRO (FCFA)', 'required' => true])
            ->add('proYearlyPriceXaf', IntegerType::class, ['label' => 'Prix annuel PRO (FCFA)', 'required' => true])
            ->add('orgSeatYearlyPriceEur', NumberType::class, ['label' => 'Organisation : prix annuel d\'une place (€)', 'scale' => 2, 'required' => true])
            ->add('orgSeatYearlyPriceXaf', IntegerType::class, ['label' => 'Organisation : prix annuel d\'une place (FCFA)', 'required' => true])
            ->add('maxHabitsFree', IntegerType::class, ['label' => 'Rituels maximum en gratuit', 'required' => true])
            ->add('maxConnectionsFree', IntegerType::class, ['label' => 'Partenaires maximum en gratuit', 'required' => true])
            ->add('bankTransferInstructions', TextareaType::class, [
                'label' => 'Coordonnées pour un virement',
                'help' => 'Affichées au client. Ex. : titulaire, banque, IBAN / RIB, BIC, et « Indiquez votre email en libellé ». Laisser vide pour masquer ce moyen de paiement.',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('mobileMoneyInstructions', TextareaType::class, [
                'label' => 'Consignes Mobile Money',
                'help' => 'Ex. : « Orange Money : 6XX XX XX XX (nom du titulaire) — MTN MoMo : … ». Laisser vide pour masquer.',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('paymentNotificationEmail', EmailType::class, [
                'label' => 'Email d\'alerte des paiements à vérifier',
                'help' => 'En plus de la notification aux administrateurs.',
                'required' => false,
            ])
        ;
        // Fonctions accessibles en gratuit (décochées = réservées à PRO)
        foreach ([
            'enableGrowthFree' => 'Plan de Croissance', 'enableDevelopmentFree' => 'Plan de Développement',
            'enableTransmissionFree' => 'Plan de Transmission', 'enableHygieneFree' => 'Plan d\'Hygiène de Vie',
            'enableMapFree' => 'Carte stratégique', 'enablePrintFree' => 'Export PDF',
            'enableMonthlyReviewsFree' => 'Revue mensuelle', 'enablePlanHistoryFree' => 'Historique des plans',
            'enableCoherenceAnalysisFree' => 'Analyse de cohérence', 'enableDiscoveryFree' => 'Découverte de soi',
        ] as $field => $label) {
            $builder->add($field, CheckboxType::class, ['label' => $label, 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AppConfig::class,
        ]);
    }
}
