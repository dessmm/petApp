<?php

namespace App\Form;

use App\Entity\Services;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use App\Entity\Appointment;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('petName', TextType::class,[
                'label' => 'Pet Name',
                'translation_domain' => 'false',
            ])
            ->add('ownerName', TextType::class,[
                'label' => 'Owner Name',
                'translation_domain' => 'false',
            ])
            ->add('ownerEmail', TextType::class,[
                'label' => 'Owner Email',
                'translation_domain' => 'false',
            ])
            ->add('appointmentDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'min' => (new \DateTime('+1 day'))->format('Y-m-d'),
                    'class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm p-2'
                ],
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan(['value' => (new \DateTime())->setTime(0,0,0)])
                ],
            ])
            ->add('appointmentTime', TimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'attr' => [
                    'min' => '09:00',
                    'max' => '16:00',
                    'step' => 3600,
                    'class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm p-2'
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('service', EntityType::class, [
                'class' => Services::class,
                'choice_label' => function (Services $service) {
                    return sprintf('%s - ₱%s', $service->getName(), number_format($service->getPrice(), 2));
                },
                'choice_attr' => function (Services $service) {
                    return ['data-price' => $service->getPrice()];
                },
                'placeholder' => 'Select a service',
                'choice_value' => 'id',
                'attr' => ['id' => 'serviceSelect', 'class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
        ]);
    }
}
