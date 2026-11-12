<?php

namespace AcMarche\Mercredi\Facture\Factory;

use AcMarche\Mercredi\Facture\FactureInterface;

interface CommunicationFactoryInterface
{
    public function generateForPresence(FactureInterface $facture): string;
}
