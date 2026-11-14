<?php

namespace AcMarche\Mercredi\Message\Handler;

use AcMarche\Mercredi\Entity\Message;
use AcMarche\Mercredi\Mailer\BulkMailer;
use AcMarche\Mercredi\Message\Factory\EmailFactory;
use AcMarche\Mercredi\Message\Repository\MessageRepository;

final readonly class MessageHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private EmailFactory $emailFactory,
        private BulkMailer $bulkMailer,
    ) {
    }

    /**
     * @return int number of mails queued
     */
    public function handle(Message $message): int
    {
        $templatedEmail = $this->emailFactory->create($message);
        $count = $this->bulkMailer->queue($templatedEmail, $message->getDestinataires());

        $this->messageRepository->persist($message);
        $this->messageRepository->flush();

        return $count;
    }
}
