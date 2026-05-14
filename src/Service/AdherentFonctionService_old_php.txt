<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Résout pour un adhérent donné :
 * - sa fédération (via comitemaore_sections.id_federation)
 * - ses fonctions dans sa fédération (colonnes de comitemaore_federations)
 * - ses fonctions dans sa section (colonnes de comitemaore_sections)
 */
class AdherentFonctionService
{
    // Colonnes de fonctions présentes dans les deux tables
    private const FONCTIONS = [
        'president'          => 'Président',
        'vice_president'     => 'Vice-président',
        'secretaire'         => 'Secrétaire',
        'tresorier'          => 'Trésorier',
        'secretaire_adjoint' => 'Secrétaire adjoint',
        'tresorier_adjoint'  => 'Trésorier adjoint',
        'administrateur1'    => 'Administrateur 1',
        'administrateur2'    => 'Administrateur 2',
        'administrateur3'    => 'Administrateur 3',
    ];

    // Colonnes de fonctions dans comitemaore_burexecnat
    private const FONCTIONS_BEN = [
        'president', 'vice_president', 'secretaire', 'tresorier',
        'secretaire_adjoint', 'tresorier_adjoint',
        'administrateur1', 'administrateur2', 'administrateur3',
    ];

    public function __construct(private readonly Connection $connection) {}

    /**
     * Retourne toutes les infos contextuelles d'un adhérent :
     * fédération, fonctions section, fonctions fédération, fonctions BEN.
     */
    public function resoudre(int $idAdht, ?int $idSection): array
    {
        $result = [
            'federation'        => null,
            'id_federation'     => null,
            'fonctions_section' => [],   // ['Président', 'Administrateur 1']
            'fonctions_fed'     => [],
            'fonctions_ben'     => [],
        ];

        // --- Fédération via la section ---
        if ($idSection) {
            $sec = $this->connection->fetchAssociative(
                'SELECT s.*, f.federation AS nom_federation
                 FROM comitemaore_sections s
                 LEFT JOIN comitemaore_federations f ON s.id_federation = f.id_federation
                 WHERE s.id_section = ?',
                [$idSection]
            );
            if ($sec) {
                $result['federation']    = $sec['nom_federation'] ?? $sec['federation'] ?? null;
                $result['id_federation'] = $sec['id_federation'] ?? null;

                // Fonctions dans la section
                foreach (self::FONCTIONS as $col => $label) {
                    if (isset($sec[$col]) && (int)$sec[$col] === $idAdht) {
                        $result['fonctions_section'][] = $label;
                    }
                }
            }
        }

        // --- Fonctions dans la fédération ---
        if ($result['id_federation']) {
            $fed = $this->connection->fetchAssociative(
                'SELECT * FROM comitemaore_federations WHERE id_federation = ?',
                [$result['id_federation']]
            );
            if ($fed) {
                foreach (self::FONCTIONS as $col => $label) {
                    if (isset($fed[$col]) && (int)$fed[$col] === $idAdht) {
                        $result['fonctions_fed'][] = $label;
                    }
                }
            }
        }

        // --- Fonctions dans le Bureau Exécutif National ---
        $ben = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_burexecnat WHERE actif = 1
             ORDER BY annee_mandat DESC LIMIT 1'
        );
        if ($ben) {
            foreach (self::FONCTIONS_BEN as $col) {
                if (isset($ben[$col]) && (int)$ben[$col] === $idAdht) {
                    $result['fonctions_ben'][] = self::FONCTIONS[$col] ?? $col;
                }
            }
        }

        return $result;
    }

    /**
     * Résout en masse pour une liste d'adhérents (évite N+1 requêtes).
     * Retourne un tableau indexé par id_adht.
     */
    public function resoudreListe(array $adherents): array
    {
        if (empty($adherents)) return [];

        // Charger toutes les sections concernées en une requête
        $idSections = array_unique(array_filter(array_column($adherents, 'id_section')));
        $sections   = [];
        if (!empty($idSections)) {
            $placeholders = implode(',', array_fill(0, count($idSections), '?'));
            $rows = $this->connection->fetchAllAssociative(
                "SELECT s.*, f.federation AS nom_federation
                 FROM comitemaore_sections s
                 LEFT JOIN comitemaore_federations f ON s.id_federation = f.id_federation
                 WHERE s.id_section IN ($placeholders)",
                array_values($idSections)
            );
            foreach ($rows as $r) {
                $sections[$r['id_section']] = $r;
            }
        }

        // Charger toutes les fédérations concernées
        $idFeds = array_unique(array_filter(array_map(
            fn($s) => $s['id_federation'] ?? null, $sections
        )));
        $federations = [];
        if (!empty($idFeds)) {
            $placeholders = implode(',', array_fill(0, count($idFeds), '?'));
            $rows = $this->connection->fetchAllAssociative(
                "SELECT * FROM comitemaore_federations WHERE id_federation IN ($placeholders)",
                array_values($idFeds)
            );
            foreach ($rows as $r) {
                $federations[$r['id_federation']] = $r;
            }
        }

        // Charger le BEN actif
        $ben = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_burexecnat WHERE actif = 1
             ORDER BY annee_mandat DESC LIMIT 1'
        ) ?: [];

        // Résoudre pour chaque adhérent
        $result = [];
        foreach ($adherents as $adht) {
            $idAdht    = (int) $adht['id_adht'];
            $idSection = $adht['id_section'] ? (int) $adht['id_section'] : null;
            $sec       = $idSection ? ($sections[$idSection] ?? null) : null;
            $idFed     = $sec ? ($sec['id_federation'] ?? null) : null;
            $fed       = $idFed ? ($federations[$idFed] ?? null) : null;

            $fnSec = [];
            $fnFed = [];
            $fnBen = [];

            if ($sec) {
                foreach (self::FONCTIONS as $col => $label) {
                    if (isset($sec[$col]) && (int)$sec[$col] === $idAdht) {
                        $fnSec[] = $label;
                    }
                }
            }
            if ($fed) {
                foreach (self::FONCTIONS as $col => $label) {
                    if (isset($fed[$col]) && (int)$fed[$col] === $idAdht) {
                        $fnFed[] = $label;
                    }
                }
            }
            if ($ben) {
                foreach (self::FONCTIONS_BEN as $col) {
                    if (isset($ben[$col]) && (int)$ben[$col] === $idAdht) {
                        $fnBen[] = self::FONCTIONS[$col] ?? $col;
                    }
                }
            }

            $result[$idAdht] = [
                'federation'        => $sec ? ($sec['nom_federation'] ?? $sec['federation'] ?? null) : null,
                'id_federation'     => $idFed,
                'fonctions_section' => $fnSec,
                'fonctions_fed'     => $fnFed,
                'fonctions_ben'     => $fnBen,
            ];
        }

        return $result;
    }

    public function labelsFonctions(): array
    {
        return self::FONCTIONS;
    }
}
