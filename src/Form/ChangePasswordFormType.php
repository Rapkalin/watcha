<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<mixed>
 */
final class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmer le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'constraints' => [
                new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
                new Assert\Length(min: 10, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.', max: 4096),
                new Assert\NotCompromisedPassword(message: 'Ce mot de passe a été exposé dans une fuite de données, choisissez-en un autre.'),
            ],
        ]);
    }
}
