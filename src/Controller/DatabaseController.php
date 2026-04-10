<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DatabaseController extends AbstractController
{
    // Types de requêtes autorisées pour la modification
    private const ALLOWED_QUERY_TYPES = ['INSERT', 'UPDATE', 'DELETE'];

    public function __construct(private readonly Connection $connection) {}

    #[Route('/database', name: 'app_database', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $result        = null;
        $error         = null;
        $affectedRows  = null;
        $tables        = [];
        $lastQuery     = '';
        $queryType     = '';

        try {
            $tables = $this->connection->createSchemaManager()->listTableNames();
        } catch (\Exception $e) {
            $error = 'Impossible de lister les tables : ' . $e->getMessage();
        }

        if ($request->isMethod('POST')) {
            $lastQuery  = trim($request->request->get('sql_query', ''));
            $queryType  = strtoupper(strtok($lastQuery, " \t\n"));

            // Sécurité : on n'autorise que INSERT, UPDATE, DELETE (pas de DROP/ALTER/TRUNCATE)
            if (!in_array($queryType, self::ALLOWED_QUERY_TYPES, true)) {
                $error = sprintf(
                    'Type de requête non autorisé "%s". Seuls INSERT, UPDATE et DELETE sont permis.',
                    htmlspecialchars($queryType)
                );
            } elseif (empty($lastQuery)) {
                $error = 'La requête SQL ne peut pas être vide.';
            } else {
                try {
                    $affectedRows = $this->connection->executeStatement($lastQuery);
                    $result       = sprintf('Requête exécutée avec succès. %d ligne(s) affectée(s).', $affectedRows);
                    $this->addFlash('success', $result);
                } catch (\Exception $e) {
                    $error = 'Erreur SQL : ' . $e->getMessage();
                }
            }
        }

        return $this->render('database/query.html.twig', [
            'tables'        => $tables,
            'result'        => $result,
            'error'         => $error,
            'affected_rows' => $affectedRows,
            'last_query'    => $lastQuery,
        ]);
    }

    // Aperçu d'une table (lecture)
    #[Route('/database/preview/{table}', name: 'app_database_preview', methods: ['GET'])]
    public function preview(string $table): Response
    {
        // Vérifie que la table existe
        $tables = $this->connection->createSchemaManager()->listTableNames();
        if (!in_array($table, $tables, true)) {
            throw $this->createNotFoundException("Table '$table' introuvable.");
        }

        $rows    = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s LIMIT 20', $this->connection->quoteIdentifier($table))
        );
        $columns = $rows ? array_keys($rows[0]) : [];

        return $this->render('database/preview.html.twig', [
            'table'   => $table,
            'rows'    => $rows,
            'columns' => $columns,
        ]);
    }
}