<?php

namespace App\Form;

use App\Entity\StaffProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StaffProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('servicesOffered', TextType::class, [
                'label' => 'Services Offered',
                'attr' => ['placeholder' => 'Enter services you offer'],
            ])
            ->add('available', CheckboxType::class, [
                'label' => 'Available',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StaffProfile::class,
        ]);
    }
}
