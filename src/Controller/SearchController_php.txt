<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('/search', name: 'app_search', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $results = [];
        $query   = '';
        $table   = '';
        $error   = null;
        $tables  = [];

        // Récupère la liste des tables disponibles
        try {
            $tables = $this->connection->createSchemaManager()->listTableNames();
        } catch (\Exception $e) {
            $error = 'Impossible de récupérer la liste des tables : ' . $e->getMessage();
        }

        if ($request->isMethod('POST')) {
            $table  = $request->request->get('table', '');
            $query  = trim($request->request->get('query', ''));
            $column = trim($request->request->get('column', '*'));
            $value  = trim($request->request->get('value', ''));

            if (empty($table)) {
                $error = 'Veuillez sélectionner une table.';
            } else {
                try {
                    // Requête SELECT sécurisée (lecture uniquement)
                    if ($value !== '') {
                        $sql = sprintf(
                            'SELECT %s FROM %s WHERE %s LIKE :val LIMIT 100',
                            $column === '*' ? '*' : $this->connection->quoteIdentifier($column),
                            $this->connection->quoteIdentifier($table),
                            $this->connection->quoteIdentifier($column)
                        );
                        $results = $this->connection->fetchAllAssociative($sql, ['val' => '%' . $value . '%']);
                    } else {
                        $sql = sprintf(
                            'SELECT %s FROM %s LIMIT 100',
                            $column === '*' ? '*' : $this->connection->quoteIdentifier($column),
                            $this->connection->quoteIdentifier($table)
                        );
                        $results = $this->connection->fetchAllAssociative($sql);
                    }
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la recherche : ' . $e->getMessage();
                }
            }
        }

        return $this->render('search/index.html.twig', [
            'tables'  => $tables,
            'results' => $results,
            'table'   => $table,
            'error'   => $error,
            'is_admin'=> $this->isGranted('ROLE_ADMIN'),
        ]);
    }
}