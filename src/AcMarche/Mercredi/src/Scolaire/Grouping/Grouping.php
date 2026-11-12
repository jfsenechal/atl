<?php

namespace AcMarche\Mercredi\Scolaire\Grouping;

use AcMarche\Mercredi\Entity\Enfant;
use AcMarche\Mercredi\Entity\Scolaire\GroupeScolaire;
use AcMarche\Mercredi\Scolaire\Utils\ScolaireUtils;
use AcMarche\Mercredi\Utils\SortUtils;

class Grouping implements GroupingInterface
{
    public function __construct(
        private ScolaireUtils $scolaireUtils,
    ) {
    }

    public function groupEnfantsForPresence(array $enfants): array
    {
        $groups = [];
        foreach ($enfants as $enfant) {
            $groupe = $this->findGroupeScolaire($enfant);
            if (!$groupe) {
                continue;
            }
            $groups[$groupe->getId()]['groupe'] = $groupe;
            $groups[$groupe->getId()]['enfants'][] = $enfant;
        }

        return SortUtils::sortGroupesScolairesByOrder($groups);
    }

    public function findGroupeScolaireByAge(float $age): ?GroupeScolaire
    {
        return null;
    }

    public function findGroupeScolaire(Enfant $enfant): ?GroupeScolaire
    {
        return $this->scolaireUtils->findGroupeScolaireEnfantByAnneeScolaire($enfant);
    }
}
