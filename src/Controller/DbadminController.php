<?php

namespace App\Controller;

use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/dbadmin')]
class DbadminController extends AbstractController
{
    private const BACKUP_DIR  = 'var/backups';
    private const FIELD_TYPES = [
        'INT', 'BIGINT', 'SMALLINT', 'TINYINT',
        'VARCHAR', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'CHAR',
        'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
        'DECIMAL', 'FLOAT', 'DOUBLE',
        'ENUM', 'SET', 'BOOLEAN', 'TINYINT(1)',
        'BLOB', 'MEDIUMBLOB', 'LONGBLOB',
        'JSON',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LogService $logService,
        private readonly string     $projectDir,
    ) {}

    // ------------------------------------------------------------------
    // PAGE PRINCIPALE
    // ------------------------------------------------------------------
    #[Route('', name: 'app_dbadmin')]
    public function index(): Response
    {
        $tables  = $this->connection->createSchemaManager()->listTableNames();
        $backups = $this->listBackups();

        return $this->render('dbadmin/index.html.twig', [
            'tables'     => $tables,
            'backups'    => $backups,
            'fieldTypes' => self::FIELD_TYPES,
        ]);
    }

    // ------------------------------------------------------------------
    // SAUVEGARDE
    // ------------------------------------------------------------------
    #[Route('/backup', name: 'app_dbadmin_backup', methods: ['POST'])]
    public function backup(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dbadmin', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $dir = $this->projectDir . '/' . self::BACKUP_DIR;
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $dbUrl    = $_ENV['DATABASE_URL'] ?? '';
        $params   = $this->parseDatabaseUrl($dbUrl);
        $filename = sprintf('backup_%s_%s.sql', $params['dbname'], date('Ymd_His'));
        $filepath = $dir . '/' . $filename;

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s %s > %s 2>&1',
            escapeshellarg($params['host']),
            escapeshellarg($params['port']),
            escapeshellarg($params['user']),
            $params['password'] ? '--password=' . escapeshellarg($params['password']) : '',
            escapeshellarg($params['dbname']),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $returnCode);

        $this->log('backup', $returnCode === 0 ? 'succes' : 'echec', null, ['file' => $filename]);

        if ($returnCode !== 0 || !file_exists($filepath) || filesize($filepath) < 10) {
            $this->addFlash('danger', 'Erreur lors de la sauvegarde. Vérifiez que mysqldump est installé.');
        } else {
            $this->addFlash('success', "Sauvegarde créée : $filename (" . $this->formatSize(filesize($filepath)) . ")");
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // TÉLÉCHARGER UN BACKUP
    // ------------------------------------------------------------------
    #[Route('/backup/download/{filename}', name: 'app_dbadmin_backup_download', methods: ['GET'])]
    public function downloadBackup(string $filename): Response
    {
        // Sécurité : pas de traversal de chemin
        $filename = basename($filename);
        $filepath = $this->projectDir . '/' . self::BACKUP_DIR . '/' . $filename;

        if (!file_exists($filepath) || !str_ends_with($filename, '.sql')) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $this->log('download_backup', 'succes', null, ['file' => $filename]);

        return $this->file($filepath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    // ------------------------------------------------------------------
    // SUPPRIMER UN BACKUP
    // ------------------------------------------------------------------
    #[Route('/backup/delete/{filename}', name: 'app_dbadmin_backup_delete', methods: ['POST'])]
    public function deleteBackup(string $filename, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('del_backup_' . $filename, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $filename = basename($filename);
        $filepath = $this->projectDir . '/' . self::BACKUP_DIR . '/' . $filename;
        if (file_exists($filepath)) unlink($filepath);

        $this->log('delete_backup', 'succes', null, ['file' => $filename]);
        $this->addFlash('success', "Sauvegarde $filename supprimée.");
        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // RESTAURATION
    // ------------------------------------------------------------------
    #[Route('/restore/{filename}', name: 'app_dbadmin_restore', methods: ['POST'])]
    public function restore(string $filename, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('restore_' . $filename, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $filename = basename($filename);
        $filepath = $this->projectDir . '/' . self::BACKUP_DIR . '/' . $filename;

        if (!file_exists($filepath)) {
            $this->addFlash('danger', 'Fichier de sauvegarde introuvable.');
            return $this->redirectToRoute('app_dbadmin');
        }

        $dbUrl  = $_ENV['DATABASE_URL'] ?? '';
        $params = $this->parseDatabaseUrl($dbUrl);

        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s %s %s < %s 2>&1',
            escapeshellarg($params['host']),
            escapeshellarg($params['port']),
            escapeshellarg($params['user']),
            $params['password'] ? '--password=' . escapeshellarg($params['password']) : '',
            escapeshellarg($params['dbname']),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $returnCode);
        $this->log('restore', $returnCode === 0 ? 'succes' : 'echec', null, ['file' => $filename]);

        if ($returnCode !== 0) {
            $this->addFlash('danger', 'Erreur lors de la restauration : ' . implode(' ', $output));
        } else {
            $this->addFlash('success', "Base restaurée depuis : $filename");
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // IMPORT SQL (fichier uploadé)
    // ------------------------------------------------------------------
    #[Route('/import', name: 'app_dbadmin_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dbadmin', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('sql_file');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_dbadmin');
        }

        if ($file->getClientOriginalExtension() !== 'sql') {
            $this->addFlash('danger', 'Seuls les fichiers .sql sont acceptés.');
            return $this->redirectToRoute('app_dbadmin');
        }

        $tmpPath = $file->getPathname();
        $sql     = file_get_contents($tmpPath);

        if (empty(trim($sql))) {
            $this->addFlash('danger', 'Le fichier SQL est vide.');
            return $this->redirectToRoute('app_dbadmin');
        }

        try {
            // Exécuter chaque instruction séparément
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s)
            );
            $count = 0;
            foreach ($statements as $stmt) {
                if (!empty(trim($stmt))) {
                    $this->connection->executeStatement($stmt);
                    $count++;
                }
            }
            $this->log('import_sql', 'succes', null, ['file' => $file->getClientOriginalName(), 'statements' => $count]);
            $this->addFlash('success', "Import réussi : $count instruction(s) exécutée(s).");
        } catch (\Exception $e) {
            $this->log('import_sql', 'echec', null, ['error' => $e->getMessage()]);
            $this->addFlash('danger', 'Erreur SQL : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // CRÉER UNE TABLE
    // ------------------------------------------------------------------
    #[Route('/table/create', name: 'app_dbadmin_table_create', methods: ['POST'])]
    public function createTable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dbadmin', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('table_name', ''));
        $fields    = $request->request->all('fields') ?? [];

        if (empty($tableName)) {
            $this->addFlash('danger', 'Nom de table invalide.');
            return $this->redirectToRoute('app_dbadmin');
        }

        $cols = ["  `id` INT(8) NOT NULL AUTO_INCREMENT"];
        foreach ($fields as $f) {
            $name     = preg_replace('/[^a-zA-Z0-9_]/', '', $f['name'] ?? '');
            $type     = strtoupper($f['type'] ?? 'VARCHAR');
            $length   = (int)($f['length'] ?? 0);
            $nullable = ($f['nullable'] ?? 'no') === 'yes' ? 'DEFAULT NULL' : 'NOT NULL';
            $default  = isset($f['default']) && $f['default'] !== '' ? "DEFAULT '" . addslashes($f['default']) . "'" : '';

            if (empty($name)) continue;

            $typeStr = in_array($type, ['VARCHAR', 'CHAR']) && $length > 0
                ? "$type($length)"
                : $type;

            $cols[] = "  `$name` $typeStr $nullable" . ($default ? " $default" : '');
        }
        $cols[] = "  PRIMARY KEY (`id`)";

        $sql = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;",
            $tableName,
            implode(",\n", $cols)
        );

        try {
            $this->connection->executeStatement($sql);
            $this->log('create_table', 'succes', null, ['table' => $tableName, 'sql' => $sql]);
            $this->addFlash('success', "Table `$tableName` créée avec succès.");
        } catch (\Exception $e) {
            $this->log('create_table', 'echec', null, ['error' => $e->getMessage()]);
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // MODIFIER UNE TABLE (ajouter/supprimer colonne, renommer)
    // ------------------------------------------------------------------
    #[Route('/table/alter', name: 'app_dbadmin_table_alter', methods: ['POST'])]
    public function alterTable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dbadmin', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('table', ''));
        $action = $request->request->get('alter_action', '');

        if (empty($table)) {
            $this->addFlash('danger', 'Table non spécifiée.');
            return $this->redirectToRoute('app_dbadmin');
        }

        try {
            switch ($action) {

                case 'add_column':
                    $col      = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_name', ''));
                    $type     = strtoupper($request->request->get('col_type', 'VARCHAR'));
                    $length   = (int) $request->request->get('col_length', 0);
                    $nullable = $request->request->get('col_nullable', 'no') === 'yes' ? 'DEFAULT NULL' : 'NOT NULL';
                    $after    = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_after', ''));

                    $typeStr = in_array($type, ['VARCHAR', 'CHAR']) && $length > 0 ? "$type($length)" : $type;
                    $sql     = "ALTER TABLE `$table` ADD COLUMN `$col` $typeStr $nullable"
                             . ($after ? " AFTER `$after`" : '');
                    $this->connection->executeStatement($sql);
                    $this->log('add_column', 'succes', null, ['table' => $table, 'column' => $col]);
                    $this->addFlash('success', "Colonne `$col` ajoutée à `$table`.");
                    break;

                case 'drop_column':
                    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_name', ''));
                    $this->connection->executeStatement("ALTER TABLE `$table` DROP COLUMN `$col`");
                    $this->log('drop_column', 'succes', null, ['table' => $table, 'column' => $col]);
                    $this->addFlash('success', "Colonne `$col` supprimée de `$table`.");
                    break;

                case 'modify_column':
                    $col      = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_name', ''));
                    $type     = strtoupper($request->request->get('col_type', 'VARCHAR'));
                    $length   = (int) $request->request->get('col_length', 0);
                    $nullable = $request->request->get('col_nullable', 'no') === 'yes' ? 'DEFAULT NULL' : 'NOT NULL';
                    $typeStr  = in_array($type, ['VARCHAR', 'CHAR']) && $length > 0 ? "$type($length)" : $type;
                    $this->connection->executeStatement("ALTER TABLE `$table` MODIFY COLUMN `$col` $typeStr $nullable");
                    $this->log('modify_column', 'succes', null, ['table' => $table, 'column' => $col]);
                    $this->addFlash('success', "Colonne `$col` modifiée dans `$table`.");
                    break;

                case 'rename_column':
                    $oldCol  = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_old', ''));
                    $newCol  = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('col_new', ''));
                    $this->connection->executeStatement("ALTER TABLE `$table` RENAME COLUMN `$oldCol` TO `$newCol`");
                    $this->log('rename_column', 'succes', null, ['table' => $table, 'old' => $oldCol, 'new' => $newCol]);
                    $this->addFlash('success', "Colonne `$oldCol` renommée en `$newCol`.");
                    break;

                case 'rename_table':
                    $newName = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('new_table_name', ''));
                    $this->connection->executeStatement("RENAME TABLE `$table` TO `$newName`");
                    $this->log('rename_table', 'succes', null, ['old' => $table, 'new' => $newName]);
                    $this->addFlash('success', "Table `$table` renommée en `$newName`.");
                    break;

                default:
                    $this->addFlash('danger', 'Action non reconnue.');
            }
        } catch (\Exception $e) {
            $this->log('alter_table', 'echec', null, ['table' => $table, 'error' => $e->getMessage()]);
            $this->addFlash('danger', 'Erreur SQL : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // SUPPRIMER UNE TABLE
    // ------------------------------------------------------------------
    #[Route('/table/drop', name: 'app_dbadmin_table_drop', methods: ['POST'])]
    public function dropTable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dbadmin', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $table   = preg_replace('/[^a-zA-Z0-9_]/', '', $request->request->get('table', ''));
        $confirm = $request->request->get('confirm_name', '');

        if ($table !== $confirm) {
            $this->addFlash('danger', 'Le nom de confirmation ne correspond pas. Suppression annulée.');
            return $this->redirectToRoute('app_dbadmin');
        }

        try {
            $this->connection->executeStatement("DROP TABLE IF EXISTS `$table`");
            $this->log('drop_table', 'succes', null, ['table' => $table]);
            $this->addFlash('success', "Table `$table` supprimée définitivement.");
        } catch (\Exception $e) {
            $this->log('drop_table', 'echec', null, ['table' => $table, 'error' => $e->getMessage()]);
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dbadmin');
    }

    // ------------------------------------------------------------------
    // COLONNES D'UNE TABLE (appel AJAX)
    // ------------------------------------------------------------------
    #[Route('/table/{table}/columns', name: 'app_dbadmin_table_columns', methods: ['GET'])]
    public function tableColumns(string $table): Response
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        try {
            $columns = $this->connection->createSchemaManager()->listTableColumns($table);
            $data    = [];
            foreach ($columns as $col) {
                $typeObj  = $col->getType();
                $typeName = method_exists($typeObj, 'getName')
                    ? $typeObj->getName()
                    : strtolower(str_replace('Type', '', (new \ReflectionClass($typeObj))->getShortName()));
                $data[] = ['name' => $col->getName(), 'type' => $typeName];
            }
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function listBackups(): array
    {
        $dir = $this->projectDir . '/' . self::BACKUP_DIR;
        if (!is_dir($dir)) return [];

        $files = glob($dir . '/*.sql') ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        return array_map(fn($f) => [
            'filename' => basename($f),
            'size'     => $this->formatSize(filesize($f)),
            'date'     => date('d/m/Y H:i:s', filemtime($f)),
        ], $files);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' Mo';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' Ko';
        return $bytes . ' o';
    }

    private function parseDatabaseUrl(string $url): array
    {
        $parsed = parse_url($url);
        return [
            'host'     => $parsed['host'] ?? '127.0.0.1',
            'port'     => $parsed['port'] ?? '3306',
            'user'     => $parsed['user'] ?? 'root',
            'password' => isset($parsed['pass']) ? urldecode($parsed['pass']) : '',
            'dbname'   => ltrim($parsed['path'] ?? '/comitemaore', '/'),
        ];
    }

    private function log(string $action, string $result, ?int $cible, array $detail = []): void
    {
        $login = $this->getUser()?->getUserIdentifier();
        $adht  = $login ? ($this->connection->fetchAssociative(
            'SELECT id_adht FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null) : null;

        $this->logService->log($action, $result, 'dbadmin', $cible, $detail,
            $adht['id_adht'] ?? null, $login);
    }
}
