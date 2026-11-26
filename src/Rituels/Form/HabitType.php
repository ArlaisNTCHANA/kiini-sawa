<?php

namespace App\Rituels\Form;

use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HabitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'habitude',
                'attr' => ['placeholder' => 'ex: Méditer 10 minutes'],
            ])
            ->add('frequency', EnumType::class, [
                'class' => HabitFrequency::class,
                'label' => 'Fréquence',
                'choice_label' => static fn (HabitFrequency $f): string => $f->label(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Habit::class,
        ]);
    }
}
