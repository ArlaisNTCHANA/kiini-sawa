<?php

namespace App\Admin\Form;

use App\Admin\Entity\AppConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
                'required' => true,
            ])
            ->add('demoPassword', TextType::class, [
                'label' => 'Mot de passe utilisateur démo',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AppConfig::class,
        ]);
    }
}
