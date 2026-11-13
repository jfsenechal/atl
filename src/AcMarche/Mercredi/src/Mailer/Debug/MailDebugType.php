<?php

namespace AcMarche\Mercredi\Mailer\Debug;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class MailDebugType extends AbstractType
{
    public const string MODE_DIRECT = 'direct';
    public const string MODE_APPLICATION = 'application';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('from', EmailType::class, [
                'label' => 'De',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
                'help' => 'Adresse d\'expéditeur utilisée pour les factures.',
            ])
            ->add('to', EmailType::class, [
                'label' => 'A',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'Mode d\'envoi',
                'expanded' => true,
                'choices' => [
                    'Directement par SMTP (affiche la conversation avec le serveur)' => self::MODE_DIRECT,
                    'Comme l\'application (Mailer, passe par la file si configuré)' => self::MODE_APPLICATION,
                ],
            ]);
    }
}
