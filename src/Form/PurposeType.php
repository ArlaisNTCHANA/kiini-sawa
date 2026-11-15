<?php

namespace App\Form;

use App\Entity\Purpose;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurposeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statement', TextareaType::class, [
                'label' => 'Ma mission (phrase clé)',
                'attr' => ['rows' => 2, 'placeholder' => 'Décrivez votre mission en une phrase claire...'],
            ])
            ->add('longStatement', TextareaType::class, [
                'label' => 'Mission détaillée',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Développez votre énoncé de mission...'],
            ])
            ->add('hypotheses', TextareaType::class, [
                'label' => 'Hypothèses de départ',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Quelles hypothèses faites-vous sur vous-même ou votre environnement ?'],
            ])
            ->add('detail', TextareaType::class, [
                'label' => 'Détails complémentaires',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ajoutez des détails sur la mise en œuvre de cette mission...'],
            ])
            ->add('proofs', TextareaType::class, [
                'label' => 'Preuves / Éléments factuels',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Quelles preuves factuelles confirment cette mission ?'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Purpose::class,
        ]);
    }
}
