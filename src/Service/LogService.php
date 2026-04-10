<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LogService
{
    public function __construct(
        private readonly Connection     $connection,
        private readonly RequestStack   $requestStack,
        private readonly LoggerInterface $logger,
    ) {}

    public function log(
        string  $action,
        string  $resultat = 'info',
        ?string $module   = null,
        ?int    $idCible  = null,
        ?array  $detail   = null,
        ?int    $idAdht   = null,
        ?string $login    = null,
    ): void {
        $request   = $this->requestStack->getCurrentRequest();
        $ip        = $request?->getClientIp();
        $sessionId = $request?->getSession()?->getId();

        $detailJson = $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;

        // --- Enregistrement en base ---
        try {
            $this->connection->insert('comitemaore_log', [
                'id_adht'     => $idAdht,
                'login_adht'  => $login,
                'ip_adresse'  => $ip,
                'action'      => $action,
                'module'      => $module,
                'id_cible'    => $idCible,
                'detail'      => $detailJson,
                'resultat'    => $resultat,
                'date_action' => (new \DateTime())->format('Y-m-d H:i:s'),
                'session_id'  => $sessionId,
            ]);
        } catch (\Exception $e) {
            // Ne pas bloquer l'appli si le log échoue
            $this->logger->error('LogService BDD error: ' . $e->getMessage());
        }

        // --- Enregistrement fichier ---
        $level   = match ($resultat) {
            'echec' => 'error',
            'succes' => 'info',
            default => 'debug',
        };
        $message = sprintf(
            '[%s] user=%s ip=%s module=%s cible=%s action=%s detail=%s',
            strtoupper($resultat),
            $login ?? 'anonymous',
            $ip ?? '-',
            $module ?? '-',
            $idCible ?? '-',
            $action,
            $detailJson ?? '{}'
        );
        $this->logger->$level($message);
    }

    /**
     * Récupère les logs du jour pour un utilisateur donné.
     */
    public function getLogsJour(?int $idAdht = null, int $limit = 100): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('comitemaore_log')
            ->where('DATE(date_action) = CURDATE()')
            ->orderBy('date_action', 'DESC')
            ->setMaxResults($limit);

        if ($idAdht !== null) {
            $qb->andWhere('id_adht = :id')->setParameter('id', $idAdht);
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Récupère tous les logs (admin).
     */
    public function getLogs(int $limit = 200, ?string $module = null, ?string $action = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('l.*, a.prenom_adht, a.nom_adht')
            ->from('comitemaore_log', 'l')
            ->leftJoin('l', 'comitemaore_adherent', 'a', 'l.id_adht = a.id_adht')
            ->orderBy('l.date_action', 'DESC')
            ->setMaxResults($limit);

        if ($module) {
            $qb->andWhere('l.module = :module')->setParameter('module', $module);
        }
        if ($action) {
            $qb->andWhere('l.action LIKE :action')->setParameter('action', '%' . $action . '%');
        }

        return $qb->fetchAllAssociative();
    }
}
