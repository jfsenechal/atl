<?php

namespace AcMarche\Mercredi\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Single source of truth for the password policy:
 * at least 12 characters, with a lowercase letter, an uppercase letter and a digit.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PasswordRequirements extends Compound
{
    public const int MIN_LENGTH = 12;

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(message: 'Veuillez saisir un mot de passe'),
            new Assert\Length(
                min: self::MIN_LENGTH,
                // max length allowed by Symfony for security reasons
                max: 4096,
                minMessage: 'Le mot de passe doit faire minimum {{ limit }} caractères',
            ),
            new Assert\Regex(
                pattern: '/\p{Ll}/u',
                message: 'Le mot de passe doit contenir au moins une minuscule',
            ),
            new Assert\Regex(
                pattern: '/\p{Lu}/u',
                message: 'Le mot de passe doit contenir au moins une majuscule',
            ),
            new Assert\Regex(
                pattern: '/\d/',
                message: 'Le mot de passe doit contenir au moins un chiffre',
            ),
        ];
    }
}
