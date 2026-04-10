<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/schema')]
class SchemaController extends AbstractController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('', name: 'app_schema')]
    public function index(): Response
    {
        $sm     = $this->connection->createSchemaManager();
        $tables = [];

        foreach ($sm->listTableNames() as $tableName) {
            $columns = $sm->listTableColumns($tableName);
            $indexes = $sm->listTableIndexes($tableName);

            $pkCols = [];
            if (isset($indexes['primary'])) {
                $pkCols = $indexes['primary']->getColumns();
            }

            $fkList = [];
            try {
                foreach ($sm->listTableForeignKeys($tableName) as $fk) {
                    foreach ($fk->getLocalColumns() as $i => $localCol) {
                        $fkList[$localCol] = [
                            'table'  => $fk->getForeignTableName(),
                            'column' => $fk->getForeignColumns()[$i] ?? '',
                        ];
                    }
                }
            } catch (\Exception) {}

            $fields = [];
            foreach ($columns as $col) {
                $typeObj = $col->getType();
                if (method_exists($typeObj, 'getName')) {
                    $typeName = $typeObj->getName();
                } else {
                    $shortClass = (new \ReflectionClass($typeObj))->getShortName();
                    $typeName   = strtolower(str_replace('Type', '', $shortClass));
                }

                $fields[] = [
                    'name' => $col->getName(),
                    'type' => $typeName,
                    'pk'   => in_array($col->getName(), $pkCols),
                    'fk'   => $fkList[$col->getName()] ?? null,
                    'null' => !$col->getNotnull(),
                ];
            }

            $tables[] = [
                'name'   => $tableName,
                'fields' => $fields,
            ];
        }

        $relations = $this->detectRelations($tables);

        return $this->render('schema/index.html.twig', [
            'tables'    => $tables,
            'relations' => $relations,
        ]);
    }

    private function detectRelations(array $tables): array
    {
        $relations  = [];
        $tableNames = array_column($tables, 'name');

        foreach ($tables as $table) {
            foreach ($table['fields'] as $field) {
                if ($field['fk']) {
                    $relations[] = [
                        'from_table' => $table['name'],
                        'from_field' => $field['name'],
                        'to_table'   => $field['fk']['table'],
                        'to_field'   => $field['fk']['column'],
                    ];
                    continue;
                }

                if (str_starts_with($field['name'], 'id_') && !$field['pk']) {
                    $suffix = substr($field['name'], 3);
                    foreach ($tableNames as $tn) {
                        if (str_contains($tn, $suffix)) {
                            $target = array_values(array_filter($tables, fn($t) => $t['name'] === $tn))[0] ?? null;
                            if ($target) {
                                $pkField = array_values(array_filter($target['fields'], fn($f) => $f['pk']))[0] ?? null;
                                if ($pkField) {
                                    $relations[] = [
                                        'from_table' => $table['name'],
                                        'from_field' => $field['name'],
                                        'to_table'   => $tn,
                                        'to_field'   => $pkField['name'],
                                    ];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $seen = [];
        return array_values(array_filter($relations, function ($r) use (&$seen) {
            $key = $r['from_table'] . $r['from_field'] . $r['to_table'];
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        }));
    }
}
