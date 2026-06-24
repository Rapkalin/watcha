<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Nom affiché',
                'required' => false,
            ])
            // Honeypot: invisible to humans (hidden in the template), tempting for bots. Any
            // submitted value means a bot filled it — the controller rejects such submissions.
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Assert\Length(min: 10, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.', max: 4096),
                    new Assert\NotCompromisedPassword(message: 'Ce mot de passe a été exposé dans une fuite de données, choisissez-en un autre.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
