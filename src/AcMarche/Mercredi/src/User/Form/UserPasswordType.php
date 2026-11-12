<?php

namespace AcMarche\Mercredi\User\Form;

use AcMarche\Mercredi\Entity\Security\User;
use AcMarche\Mercredi\Validator\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $formBuilder, array $options): void
    {
        $formBuilder
            ->add(
                'plainPassword',
                TextType::class,
                [
                    'label' => 'Mot de passe',
                    'help' => 'Minimum 12 caractères, avec une minuscule, une majuscule et un chiffre',
                    'constraints' => [
                        new PasswordRequirements(),
                    ],
                ]
            );
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults(
            [
                'data_class' => User::class,
            ]
        );
    }
}
