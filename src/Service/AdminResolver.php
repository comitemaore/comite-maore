<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Résout l'administrateur connecté depuis la table comitemaore_admin.
 * Utilisé par le workflow d'approbation à la place de comitemaore_adherent.
 */
class AdminResolver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Security   $security,
    ) {}

    /**
     * Retourne la fiche admin de l'utilisateur connecté depuis comitemaore_admin,
     * ou null si l'utilisateur n'est pas référencé dans cette table.
     */
    public function current(): ?array
    {
        $login = $this->security->getUser()?->getUserIdentifier();
        if (!$login) return null;

        return $this->connection->fetchAssociative(
            "SELECT a.*, s.section AS nom_section
             FROM comitemaore_admin a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE a.login_admin = ? AND a.actif = 1",
            [$login]
        ) ?: null;
    }

    /**
     * Retourne la fiche admin, ou un tableau de secours si l'utilisateur
     * n'est pas dans comitemaore_admin (cas root/admin non encore enregistré).
     */
    public function currentOrFallback(): array
    {
        $admin = $this->current();
        if ($admin !== null) {
            return $admin;
        }

        // Fallback : prend le premier admin actif de la table
        $login    = $this->security->getUser()?->getUserIdentifier() ?? 'unknown';
        $fallback = $this->connection->fetchAssociative(
            "SELECT a.*, s.section AS nom_section
             FROM comitemaore_admin a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE a.actif = 1
             ORDER BY a.id_admin ASC LIMIT 1"
        );

        if ($fallback) {
            return array_merge($fallback, ['login_admin' => $login]);
        }

        // Dernier recours : table vide
        return [
            'id_admin'    => 0,
            'login_admin' => $login,
            'id_section'  => null,
            'nom_section' => null,
            'prenom_admin'=> $login,
            'nom_admin'   => '',
            'rang'        => 1,
        ];
    }

    /**
     * Vérifie si l'utilisateur connecté est admin d'une section donnée.
     */
    public function isAdminOfSection(int $idSection): bool
    {
        $admin = $this->current();
        return $admin !== null && (int) $admin['id_section'] === $idSection;
    }

    /**
     * Retourne tous les admins actifs d'une section.
     */
    public function adminsOfSection(int $idSection): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT * FROM comitemaore_admin WHERE id_section = ? AND actif = 1 ORDER BY rang",
            [$idSection]
        );
    }

    /**
     * Retourne tous les admins actifs de sections différentes de celle donnée.
     */
    public function adminsOtherSections(int $idSection): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT a.*, s.section AS nom_section
             FROM comitemaore_admin a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE a.id_section != ? AND a.actif = 1
             ORDER BY s.section, a.rang",
            [$idSection]
        );
    }
}
