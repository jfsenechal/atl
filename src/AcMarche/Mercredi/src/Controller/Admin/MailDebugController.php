<?php

namespace AcMarche\Mercredi\Controller\Admin;

use AcMarche\Mercredi\Mailer\Debug\MailDebugger;
use AcMarche\Mercredi\Mailer\Debug\MailDebugType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MERCREDI_ADMIN')]
#[Route(path: '/mail-debug')]
final class MailDebugController extends AbstractController
{
    public function __construct(
        private readonly MailDebugger $mailDebugger,
    ) {
    }

    #[Route(path: '/', name: 'mercredi_admin_mail_debug', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(MailDebugType::class, [
            'from' => $this->mailDebugger->senderAddress(),
            'to' => $this->getUser()?->getUserIdentifier(),
            'subject' => 'Test envoi mail Mercredi',
            'mode' => MailDebugType::MODE_DIRECT,
        ]);
        $form->handleRequest($request);

        $sendResult = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $this->mailDebugger->createTestEmail($data['from'], $data['to'], $data['subject']);
            $sendResult = MailDebugType::MODE_DIRECT === $data['mode']
                ? $this->mailDebugger->sendDirect($email)
                : $this->mailDebugger->sendLikeTheApplication($email);
        }

        return $this->render('@AcMarcheMercrediAdmin/mail_debug/index.html.twig', [
            'form' => $form,
            'sendResult' => $sendResult,
            'consumeOutput' => null,
            ...$this->state(),
        ]);
    }

    #[Route(path: '/consume', name: 'mercredi_admin_mail_debug_consume', methods: ['POST'])]
    #[IsCsrfTokenValid('mail-debug-consume')]
    public function consume(): Response
    {
        $output = $this->mailDebugger->consumeQueue();

        return $this->render('@AcMarcheMercrediAdmin/mail_debug/index.html.twig', [
            'form' => $this->createForm(MailDebugType::class, [
                'from' => $this->mailDebugger->senderAddress(),
                'subject' => 'Test envoi mail Mercredi',
                'mode' => MailDebugType::MODE_DIRECT,
            ]),
            'sendResult' => null,
            'consumeOutput' => $output,
            ...$this->state(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return [
            'dsn' => $this->mailDebugger->maskedDsn(),
            'sender' => $this->mailDebugger->senderAddress(),
            'contact' => $this->mailDebugger->contactAddress(),
            'routing' => $this->mailDebugger->mailRouting(),
            'asyncQueue' => $this->mailDebugger->queue('async'),
            'failedQueue' => $this->mailDebugger->queue('failed'),
        ];
    }
}
