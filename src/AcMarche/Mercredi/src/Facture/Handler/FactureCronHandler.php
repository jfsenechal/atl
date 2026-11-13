<?php

namespace AcMarche\Mercredi\Facture\Handler;

use AcMarche\Mercredi\Facture\Factory\FactureFactory;
use AcMarche\Mercredi\Facture\Repository\FactureCronRepository;
use AcMarche\Mercredi\Facture\Repository\FactureRepository;
use AcMarche\Mercredi\Mailer\Factory\AdminEmailFactory;
use AcMarche\Mercredi\Mailer\Factory\FactureEmailFactory;
use AcMarche\Mercredi\Tuteur\Utils\TuteurUtils;
use Carbon\Carbon;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class FactureCronHandler
{
    public function __construct(
        private FactureCronRepository $factureCronRepository,
        private FactureRepository $factureRepository,
        private FactureEmailFactory $factureEmailFactory,
        private FactureFactory $factureFactory,
        private AdminEmailFactory $adminEmailFactory,
        private TransportInterface $transport,
        #[Target('mailer')]
        private RateLimiterFactoryInterface $mailerLimiter,
    ) {
    }

    public function execute(int $max = 40): array
    {
        $result = [];
        $crons = $this->factureCronRepository->findNotDone();

        $now = Carbon::now();
        foreach ($crons as $cron) {
            if ($cron->getDateLastSync()) {

                $lastSync = new Carbon($cron->getDateLastSync());

                if ($now->diffInMinutes($lastSync, true) < 60) {
                    $result[] = [
                        'message' => ' Last sync too early. Minutes: '.$now->diffInMinutes($lastSync, true),
                    ];
                    continue;
                }
            }

            $cron->setDateLastSync($now->modify('-5 minutes'));
            $this->factureCronRepository->flush();

            $factures = $this->factureRepository->findFacturesByMonthNotSendAndNotPaid($cron->getMonthDate());

            if (\count($factures) === 0) {
                $cron->setDone(true);
                $this->factureCronRepository->flush();
                $result[] = ['message' => $cron->getId().' 0 facture '];
                continue;
            }

            $messageBase = $this->factureEmailFactory->messageFacture(
                $cron->getSubject(),
                $cron->getBody()
            );
            $i = 0;
            foreach ($factures as $facture) {
                $messageFacture = clone $messageBase; //sinon attachs multiple

                try {
                    $this->factureFactory->createOnePdf($facture, $cron->getMonthDate(), $cron->force_send);
                } catch (\Exception $e) {
                    $error = 'Impossible de générer le pdf pour la facture: '.$facture->getId().' '.$e->getMessage();
                    $this->sendAlert('Erreur pdf facture', $error);
                    $result[] = ['message' => $facture->getId().' error create pdf '.$e->getMessage()];
                    continue;
                }

                $tuteur = $facture->getTuteur();
                $emails = TuteurUtils::getEmailsOfOneTuteur($tuteur);

                if (\count($emails) < 1) {
                    $error = 'Pas de mail pour la facture: '.$facture->getId();
                    $this->sendAlert('Erreur envoie facture', $error);
                    $result[] = ['message' => $facture->getId().' pas de mail '.$error];
                    continue;
                }

                $this->factureEmailFactory->setTos($messageFacture, $emails);

                try {
                    $this->factureEmailFactory->attachFactureFromPath($messageFacture, $facture);
                } catch (\Exception $e) {
                    $error = 'Pas de pièce jointe pour la facture: '.$facture->getId().' '.$e->getMessage();
                    $this->sendAlert('Erreur envoie facture', $error);
                    $result[] = ['message' => $facture->getId().' error attach '.$error];
                    continue;
                }

                // Same hourly quota as the Messenger worker: the rest goes on the next run
                if (!$this->mailerLimiter->create()->consume()->isAccepted()) {
                    $result[] = ['message' => 'count '.$i];
                    $result[] = ['message' => 'Limite horaire d\'envoi de mails atteinte, suite au prochain passage'];

                    return $result;
                }

                try {
                    // Envoi direct par le transport : pas de file Messenger, la facture
                    // n'est marquée envoyée que si le serveur SMTP a accepté le mail
                    $this->transport->send($messageFacture);
                } catch (TransportExceptionInterface $e) {
                    $error = 'Facture num '.$facture->getId().' '.$e->getMessage();
                    $this->sendAlert('Erreur envoie facture', $error);
                    $result[] = ['message' => $facture->getId().' error envoie '.$error];
                    continue;
                }

                $facture->setEnvoyeA(implode(', ', $emails));
                $facture->setEnvoyeLe(new \DateTime());
                $this->factureRepository->flush();
                ++$i;
                if ($i > $max) {
                    break;
                }
            }
            $result[] = ['message' => 'count '.$i];
            //$cron->addResult($result);
        }

        return $result;
    }

    public function sendResult(array $result): void
    {
        $message = $this->adminEmailFactory->messageToJf('Result envoie facture', json_encode($result));
        $this->transport->send($message);
    }

    /**
     * Alerte envoyée en direct à l'admin. Un échec d'envoi ne doit pas interrompre
     * le lot : l'erreur figure déjà dans le résultat de l'exécution.
     */
    private function sendAlert(string $subject, string $error): void
    {
        try {
            $this->transport->send($this->adminEmailFactory->messageAlert($subject, $error));
        } catch (TransportExceptionInterface) {
        }
    }
}
