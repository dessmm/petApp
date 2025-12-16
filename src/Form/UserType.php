<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter a username.']),
                    new Assert\Length(['max' => 180]),
                ],
                'attr' => ['class' => 'w-full px-4 py-3 bg-white border rounded-lg focus:ring-[var(--gold)]'],
            ])

            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
            ])

            ->add('email', TextType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => ['class' => 'w-full px-4 py-3 bg-white border rounded-lg focus:ring-[var(--gold)]'],
            ])

            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                    'Customer' => 'ROLE_USER',
                ],
                'expanded' => false,
                'multiple' => false,
                'mapped' => false,
                'label' => 'User Role',
                'data' => $options['data']->getRoles()[0] ?? 'ROLE_USER',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm p-2'
                ],
            ])

            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => $options['is_new'],   // password required only on creation
                'constraints' => $options['is_new'] ? [
                    new Assert\NotBlank(['message' => 'Please provide a password.']),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                    ])
                ] : [
                    // optional on edit, but if provided it must be at least 8 chars
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                    ])
                ],
            ])

            ->add('isActive', CheckboxType::class, [
                'label' => 'Active Account',
                'required' => false,
                // When creating a new user we hide the field in the template; do not map it
                // so a missing checkbox in POST doesn't flip the entity to false.
                'mapped' => !$options['is_new'],
                'data' => $options['data'] ? $options['data']->getIsActive() : true,
            ]);
            
            // Admin control to archive/unarchive user accounts (not mapped to entity)
            $builder->add('archived', CheckboxType::class, [
                'label' => 'Archived',
                'mapped' => false,
                'required' => false,
                'data' => $options['data'] && method_exists($options['data'], 'getArchivedAt') ? ($options['data']->getArchivedAt() !== null) : false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false, // custom option
            // Ensure CSRF protection uses a stable token id for this form
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            // token id should be unique to this form's intent
            'csrf_token_id' => 'admin_user_create',
        ]);
    }
}
