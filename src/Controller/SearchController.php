<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    private const LIMITE_OPTIONS = [25, 50, 100, 250, 500, 1000, 0];

    public function __construct(private readonly Connection $connection) {}

    // ------------------------------------------------------------------
    // PAGE PRINCIPALE — recherche avancée dans les tables
    // ------------------------------------------------------------------
    #[Route('/search', name: 'app_search', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $results    = [];
        $table      = '';
        $error      = null;
        $tables     = [];
        $totalCount = null;
        $isAdmin    = $this->isGranted('ROLE_ADMIN');
        $page       = max(1, (int) $request->request->get('page', 1));
        $limite     = (int) $request->request->get('limite', 50);
        $column     = trim($request->request->get('column', '*'));
        $value      = trim($request->request->get('value', ''));

        try {
            $tables = $this->connection->createSchemaManager()->listTableNames();
        } catch (\Exception $e) {
            $error = 'Impossible de récupérer la liste des tables : ' . $e->getMessage();
        }

        if ($request->isMethod('POST')) {
            $table = $request->request->get('table', '');

            if ($limite === 0 && !$isAdmin) $limite = 500;
            if (!in_array($limite, self::LIMITE_OPTIONS, true)) $limite = 50;

            if (empty($table)) {
                $error = 'Veuillez sélectionner une table.';
            } else {
                try {
                    $tblSql    = $this->connection->quoteIdentifier($table);
                    $colSql    = ($column === '*' || $column === '')
                        ? '*'
                        : $this->connection->quoteIdentifier($column);
                    $offset    = $limite > 0 ? ($page - 1) * $limite : 0;
                    $hasFilter = ($value !== '' && $column !== '' && $column !== '*');

                    if ($hasFilter) {
                        $colWhere   = $this->connection->quoteIdentifier($column);
                        $totalCount = (int) $this->connection->fetchOne(
                            "SELECT COUNT(*) FROM $tblSql WHERE $colWhere LIKE :val",
                            ['val' => '%' . $value . '%']
                        );
                        $sql     = "SELECT $colSql FROM $tblSql WHERE $colWhere LIKE :val"
                                 . ($limite > 0 ? " LIMIT $limite OFFSET $offset" : '');
                        $rows    = $this->connection->fetchAllAssociative(
                            $sql, ['val' => '%' . $value . '%']
                        );
                    } else {
                        $totalCount = (int) $this->connection->fetchOne(
                            "SELECT COUNT(*) FROM $tblSql"
                        );
                        $sql     = "SELECT $colSql FROM $tblSql"
                                 . ($limite > 0 ? " LIMIT $limite OFFSET $offset" : '');
                        $rows    = $this->connection->fetchAllAssociative($sql);
                    }

                    // Enrichir chaque ligne avec les liens fiche + finance
                    $results = array_map(function(array $row) use ($table): array {
                        $row['_liens'] = $this->resoudreLiens($table, $row);
                        return $row;
                    }, $rows);
                } catch (\Exception $e) {
                    $error = 'Erreur : ' . $e->getMessage();
                }
            }
        }

        $totalPages = ($totalCount !== null && $limite > 0)
            ? (int) ceil($totalCount / $limite)
            : 1;

        // Colonnes disponibles pour la table sélectionnée (pour l'autocomplétion)
        $colonnes = [];
        if ($table) {
            try {
                $colonnes = array_map(
                    fn($c) => $c->getName(),
                    $this->connection->createSchemaManager()->listTableColumns($table)
                );
            } catch (\Exception) {}
        }

        return $this->render('search/index.html.twig', [
            'tables'        => $tables,
            'results'       => $results,
            'table'         => $table,
            'error'         => $error,
            'is_admin'      => $isAdmin,
            'total_count'   => $totalCount,
            'total_pages'   => $totalPages,
            'page'          => $page,
            'limite'        => $limite,
            'limite_options'=> self::LIMITE_OPTIONS,
            'column'        => $column,
            'value'         => $value,
            'colonnes'      => $colonnes,
        ]);
    }

    // ------------------------------------------------------------------
    // AUTOCOMPLETE AJAX — suggestions en temps réel
    // Cherche dans les tables principales sur les champs texte courants
    // ------------------------------------------------------------------
    #[Route('/search/autocomplete', name: 'app_search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $q     = trim($request->query->get('q', ''));
        $table = trim($request->query->get('table', ''));

        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $suggestions = [];

        // Si une table est sélectionnée : chercher dans ses colonnes texte
        if ($table) {
            $suggestions = $this->chercherDansTable($table, $q);
        } else {
            // Sinon : recherche globale dans les tables principales
            $suggestions = $this->rechercheGlobale($q);
        }

        return new JsonResponse($suggestions);
    }

    // ------------------------------------------------------------------
    // COLONNES AJAX — retourne les colonnes d'une table
    // ------------------------------------------------------------------
    #[Route('/search/colonnes/{table}', name: 'app_search_colonnes', methods: ['GET'])]
    public function colonnes(string $table): JsonResponse
    {
        try {
            $cols = array_map(
                fn($c) => [
                    'nom'  => $c->getName(),
                    'type' => $c->getType()->getName(),
                ],
                $this->connection->createSchemaManager()->listTableColumns($table)
            );
            return new JsonResponse($cols);
        } catch (\Exception) {
            return new JsonResponse([]);
        }
    }

    // ------------------------------------------------------------------
    // Résoudre les liens fiche + finance pour une ligne de résultat
    // ------------------------------------------------------------------
    private function resoudreLiens(string $table, array $row): array
    {
        $lienFiche    = null;
        $lienFinance  = null;

        switch ($table) {
            case 'comitemaore_adherent':
                $id = $row['id_adht'] ?? null;
                if ($id) {
                    $lienFiche   = '/adherent/' . $id;
                    $lienFinance = '/finance/' . $id;
                }
                break;

            case 'comitemaore_sections':
                $id = $row['id_section'] ?? null;
                if ($id) {
                    $lienFiche   = '/fiche/section/' . $id;
                    $lienFinance = '/finance/section/' . $id;
                }
                break;

            case 'comitemaore_federations':
                $id = $row['id_federation'] ?? null;
                if ($id) {
                    $lienFiche   = '/fiche/federation/' . $id;
                    $lienFinance = '/finance/federation/' . $id;
                }
                break;

            case 'comitemaore_burexecnat':
                $id = $row['id_burexec'] ?? null;
                if ($id) {
                    $lienFiche   = '/organigramme/bureau';
                    $lienFinance = '/finance/bureau-national';
                }
                break;

            case 'finance_adh':
                $id = $row['id_adht'] ?? null;
                if ($id) {
                    $lienFiche   = '/adherent/' . $id;
                    $lienFinance = '/finance/' . $id;
                }
                break;

            case 'tab_finance':
                $idSec = $row['id_section'] ?? null;
                $idFed = $row['id_federation'] ?? null;
                if ($idSec) {
                    $lienFiche   = '/fiche/section/' . $idSec;
                    $lienFinance = '/finance/section/' . $idSec;
                } elseif ($idFed) {
                    $lienFiche   = '/fiche/federation/' . $idFed;
                    $lienFinance = '/finance/federation/' . $idFed;
                }
                break;

            case 'comitemaore_approbation':
                $id = $row['id_approbation'] ?? null;
                if ($id) $lienFiche = '/approbation/' . $id;
                break;

            case 'comitemaore_document':
            case 'comitemaore_cv':
            case 'comitemaore_cotisation_due':
                $id = $row['id_adht'] ?? null;
                if ($id) {
                    $lienFiche   = '/adherent/' . $id;
                    $lienFinance = '/finance/' . $id;
                }
                break;
        }

        return ['fiche' => $lienFiche, 'finance' => $lienFinance];
    }

    // ------------------------------------------------------------------
    // Recherche dans les colonnes texte d'une table donnée
    // ------------------------------------------------------------------
    private function chercherDansTable(string $table, string $q): array
    {
        $suggestions = [];
        $like        = '%' . $q . '%';

        try {
            $cols     = $this->connection->createSchemaManager()->listTableColumns($table);
            $tblSql   = $this->connection->quoteIdentifier($table);
            $textCols = [];

            foreach ($cols as $col) {
                $type = strtolower((string) $col->getType());
                if (str_contains($type, 'string')
                    || str_contains($type, 'text')
                    || str_contains($type, 'enum')) {
                    $textCols[] = $col->getName();
                }
            }

            if (empty($textCols)) return [];

            // Construire WHERE col1 LIKE ? OR col2 LIKE ? ...
            $whereParts = array_map(
                fn($c) => $this->connection->quoteIdentifier($c) . ' LIKE :q',
                $textCols
            );
            $where  = implode(' OR ', $whereParts);
            $rows   = $this->connection->fetchAllAssociative(
                "SELECT * FROM $tblSql WHERE $where LIMIT 10",
                ['q' => $like]
            );

            foreach ($rows as $row) {
                // Trouver la valeur correspondante dans les colonnes texte
                foreach ($textCols as $col) {
                    $val = $row[$col] ?? '';
                    if ($val && stripos((string)$val, $q) !== false) {
                        $label = "$val";
                        // Ajouter contexte si possible (nom + prénom, etc.)
                        if (isset($row['nom_adht']) && $col !== 'nom_adht') {
                            $label .= ' — ' . $row['nom_adht']
                                . ' ' . ($row['prenom_adht'] ?? '');
                        }
                        $suggestions[] = [
                            'valeur'  => $val,
                            'label'   => $label,
                            'colonne' => $col,
                            'table'   => $table,
                        ];
                        break;
                    }
                }
            }
        } catch (\Exception) {}

        return array_slice($suggestions, 0, 10);
    }

    // ------------------------------------------------------------------
    // Recherche globale dans les tables principales
    // ------------------------------------------------------------------
    private function rechercheGlobale(string $q): array
    {
        $like        = '%' . $q . '%';
        $suggestions = [];

        // Adhérents
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id_adht, nom_adht, prenom_adht, NIN_adh, email_adht
             FROM comitemaore_adherent
             WHERE nom_adht LIKE :q OR prenom_adht LIKE :q
                OR NIN_adh LIKE :q OR email_adht LIKE :q
             LIMIT 6",
            ['q' => $like]
        );
        foreach ($rows as $r) {
            $suggestions[] = [
                'valeur'   => $r['nom_adht'] . ' ' . $r['prenom_adht'],
                'label'    => '👤 ' . $r['nom_adht'] . ' ' . $r['prenom_adht']
                            . ' — NIN : ' . ($r['NIN_adh'] ?? '—'),
                'colonne'  => 'nom_adht',
                'table'    => 'comitemaore_adherent',
                'lien'     => '/adherent/' . $r['id_adht'],
                'categorie'=> 'Adhérents',
            ];
        }

        // Sections
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id_section, section, federation
             FROM comitemaore_sections
             WHERE section LIKE :q OR federation LIKE :q
             LIMIT 4",
            ['q' => $like]
        );
        foreach ($rows as $r) {
            $suggestions[] = [
                'valeur'   => $r['section'],
                'label'    => '📍 ' . $r['section'] . ' (' . $r['federation'] . ')',
                'colonne'  => 'section',
                'table'    => 'comitemaore_sections',
                'lien'     => '/fiche/section/' . $r['id_section'],
                'categorie'=> 'Sections',
            ];
        }

        // Fédérations
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id_federation, federation, nom_complet
             FROM comitemaore_federations
             WHERE federation LIKE :q OR nom_complet LIKE :q
             LIMIT 4",
            ['q' => $like]
        );
        foreach ($rows as $r) {
            $suggestions[] = [
                'valeur'   => $r['federation'],
                'label'    => '🏘 ' . $r['federation']
                            . ($r['nom_complet'] ? ' — ' . $r['nom_complet'] : ''),
                'colonne'  => 'federation',
                'table'    => 'comitemaore_federations',
                'lien'     => '/fiche/federation/' . $r['id_federation'],
                'categorie'=> 'Fédérations',
            ];
        }

        return $suggestions;
    }
}
