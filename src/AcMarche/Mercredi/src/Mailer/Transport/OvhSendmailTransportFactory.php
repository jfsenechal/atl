<?php

namespace AcMarche\Mercredi\Mailer\Transport;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * MAILER_DSN=ovh://default : sendmail_path of php.ini, see OvhSendmailTransport.
 */
#[AutoconfigureTag('mailer.transport_factory')]
final class OvhSendmailTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'ovh', $this->getSupportedSchemes());
        }

        $command = ini_get('sendmail_path') ?: '/usr/sbin/sendmail -t -i';
        // Events and logs are handled by the outer transport, once per mail
        $sendmail = new SendmailTransport($command);

        return new OvhSendmailTransport($sendmail, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['ovh'];
    }
}
