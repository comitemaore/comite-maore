<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Gère la règle de bypass du workflow d'approbation.
 *
 * Règle : si moins de 20 sections ont leurs 3 administrateurs
 * (administrateur1, administrateur2, administrateur3) tous renseignés,
 * l'ajout d'adhérent est exécuté directement sans passer par le vote.
 */
class ApprobationService
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * Retourne true si le bypass est actif (moins de 20 sections complètes).
     */
    public function bypassActif(): bool
    {
        return $this->nbSectionsCompletes() < 20;
    }

    /**
     * Nombre de sections ayant les 3 admins renseignés.
     */
    public function nbSectionsCompletes(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comitemaore_sections
             WHERE administrateur1 IS NOT NULL
               AND administrateur2 IS NOT NULL
               AND administrateur3 IS NOT NULL'
        );
    }

    /**
     * Détail pour affichage : nombre de sections complètes / total.
     */
    public function statutBypass(): array
    {
        $completes = $this->nbSectionsCompletes();
        $total     = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM comitemaore_sections');

        return [
            'bypass_actif' => $completes < 20,
            'completes'    => $completes,
            'total'        => $total,
            'manquantes'   => max(0, 20 - $completes),
        ];
    }
}
