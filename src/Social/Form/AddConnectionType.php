<?php

namespace App\Social\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddConnectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail du partenaire',
                'attr' => [
                    'placeholder' => 'partenaire@exemple.com',
                    'class' => 'w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-teal-600 focus:border-teal-600'
                ],
                'constraints' => [
                    new NotBlank(message: 'L\'adresse e-mail est obligatoire.'),
                    new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class, returns simple array
        ]);
    }
}
