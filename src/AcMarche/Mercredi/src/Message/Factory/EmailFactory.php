<?php

namespace AcMarche\Mercredi\Message\Factory;

use AcMarche\Mercredi\Entity\Message;
use AcMarche\Mercredi\Mailer\TemplatedEmailFactory;
use AcMarche\Mercredi\Organisation\Traits\OrganisationPropertyInitTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class EmailFactory
{
    use OrganisationPropertyInitTrait;

    public function create(Message $message): TemplatedEmail
    {
        $notification = TemplatedEmailFactory::asPublicEmailJf();
        $notification->subject($message->getSujet())
            ->from($this->getEmailSenderAddress())
            ->htmlTemplate('@AcMarcheMercrediEmail/admin/mail.html.twig')
            ->context(
                [
                    'texte' => $message->getTexte(),
                    'organisation' => $this->organisation,
                ]
            );

        /**
         * Pieces jointes.
         */
        if (null !== ($uploadedFile = $message->getFile())) {
            $notification->attachFromPath(
                $uploadedFile->getRealPath(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getClientMimeType()
            );
        }

        return $notification;
    }
}
