<?php

namespace App\PlanDeVie\Form;

use App\PlanDeVie\Entity\Vision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('horizon', TextType::class, [
                'label' => 'Horizon temporel',
                'attr' => ['placeholder' => 'Ex: 5 ans, 10 ans, Horizon 2030...'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Énoncé de la vision',
                'attr' => ['rows' => 4, 'placeholder' => 'Décrivez votre vision pour cet horizon temporel...'],
            ])
            ->add('beneficiaries', TextareaType::class, [
                'label' => 'Bénéficiaires',
                'attr' => ['rows' => 3, 'placeholder' => 'Qui bénéficiera de la réalisation de cette vision ?'],
            ])
            ->add('indicators', TextareaType::class, [
                'label' => 'Indicateurs de succès',
                'attr' => ['rows' => 3, 'placeholder' => 'Comment saurez-vous que cette vision est accomplie ?'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vision::class,
        ]);
    }
}
