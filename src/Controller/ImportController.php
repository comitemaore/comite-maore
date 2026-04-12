<?php

namespace App\Controller;

use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/import')]
class ImportController extends AbstractController
{
    private const FONCTIONS = [
        'president', 'vice_president', 'secretaire', 'tresorier',
        'secretaire_adjoint', 'tresorier_adjoint',
        'administrateur1', 'administrateur2', 'administrateur3',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LogService $logService,
    ) {}

    // ------------------------------------------------------------------
    // PAGE PRINCIPALE
    // ------------------------------------------------------------------
    #[Route('', name: 'app_import')]
    public function index(): Response
    {
        return $this->render('import/index.html.twig', [
            'fonctions' => self::FONCTIONS,
        ]);
    }

    // ------------------------------------------------------------------
    // TRAITEMENT
    // ------------------------------------------------------------------
    #[Route('/process/{type}', name: 'app_import_process', methods: ['POST'])]
    public function process(string $type, Request $request): Response
    {
        if (!in_array($type, ['bureau', 'federations', 'sections'], true)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('import_' . $type, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('csv_file');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_import');
        }
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['csv'], true)) {
            $this->addFlash('danger', 'Seuls les fichiers .csv sont acceptés.');
            return $this->redirectToRoute('app_import');
        }

        try {
            $rapport = match ($type) {
                'bureau'      => $this->importerBureau($file->getPathname()),
                'federations' => $this->importerFederations($file->getPathname()),
                'sections'    => $this->importerSections($file->getPathname()),
            };

            $this->logService->log("import_csv_{$type}", 'succes', 'import', null, [
                'fichier' => $file->getClientOriginalName(),
                'resume'  => $rapport['resume'],
            ], null, $this->getUser()?->getUserIdentifier());

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('app_import');
        }

        return $this->render('import/rapport.html.twig', [
            'rapport' => $rapport,
            'type'    => $type,
            'fichier' => $file->getClientOriginalName(),
        ]);
    }

    // ------------------------------------------------------------------
    // IMPORT BUREAU — 1 ligne avec toutes les colonnes fonctions
    // ------------------------------------------------------------------
    private function importerBureau(string $filepath): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        if (empty($lignes)) {
            throw new \RuntimeException('Le fichier CSV est vide.');
        }

        // Récupérer ou créer le bureau actif
        $bureau = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_burexecnat WHERE actif = 1 ORDER BY annee_mandat DESC LIMIT 1'
        );
        if (!$bureau) {
            $this->connection->insert('comitemaore_burexecnat', [
                'nom_bureau'   => 'Bureau Exécutif National',
                'annee_mandat' => (int) date('Y'),
                'actif'        => 1,
            ]);
            $idBureau = (int) $this->connection->lastInsertId();
        } else {
            $idBureau = $bureau['id_burexec'];
        }

        foreach ($lignes as $i => $ligne) {
            $numLigne = $i + 2;
            $maj      = [];

            foreach (self::FONCTIONS as $fn) {
                $col   = $fn . '_id';
                $idVal = isset($ligne[$col]) ? (int) trim($ligne[$col]) : 0;

                if ($idVal <= 0) {
                    $rapport['ignores'][] = "Ligne $numLigne : colonne $col vide ou absente — ignorée.";
                    $rapport['nb_ignores']++;
                    continue;
                }

                $err = $this->verifierAdherent($idVal, $numLigne, $fn);
                if ($err) {
                    $rapport['erreurs'][] = $err;
                    $rapport['nb_erreurs']++;
                    continue;
                }

                $maj[$fn] = $idVal;
                $rapport['succes'][] = "Ligne $numLigne : $fn = id_adht $idVal";
                $rapport['nb_succes']++;
            }

            if (!empty($maj)) {
                $maj['modifie_par'] = $this->getUser()?->getUserIdentifier();
                $this->connection->update('comitemaore_burexecnat', $maj, ['id_burexec' => $idBureau]);
            }
        }

        $rapport['resume'] = "Bureau national : {$rapport['nb_succes']} fonction(s) mises à jour, {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // IMPORT FÉDÉRATIONS — 1 ligne par fédération
    // ------------------------------------------------------------------
    private function importerFederations(string $filepath): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        $this->verifierColonnesRequises($lignes, ['federation']);

        foreach ($lignes as $i => $ligne) {
            $numLigne = $i + 2;
            $codeFed  = trim($ligne['federation'] ?? '');

            if (empty($codeFed)) {
                $rapport['erreurs'][] = "Ligne $numLigne : colonne 'federation' vide.";
                $rapport['nb_erreurs']++;
                continue;
            }

            $fed = $this->connection->fetchAssociative(
                'SELECT id_federation FROM comitemaore_federations WHERE federation = ?', [$codeFed]
            );
            if (!$fed) {
                $rapport['erreurs'][] = "Ligne $numLigne : fédération « $codeFed » introuvable dans comitemaore_federations.";
                $rapport['nb_erreurs']++;
                continue;
            }
            $idFed = $fed['id_federation'];
            $maj   = [];

            foreach (self::FONCTIONS as $fn) {
                $col   = $fn . '_id';
                $idVal = isset($ligne[$col]) ? (int) trim($ligne[$col]) : 0;

                if ($idVal <= 0) {
                    $rapport['ignores'][] = "Ligne $numLigne ($codeFed) : colonne $col vide — ignorée.";
                    $rapport['nb_ignores']++;
                    continue;
                }

                $err = $this->verifierAdherent($idVal, $numLigne, "$codeFed.$fn");
                if ($err) {
                    $rapport['erreurs'][] = $err;
                    $rapport['nb_erreurs']++;
                    continue;
                }

                $maj[$fn] = $idVal;
                $rapport['succes'][] = "Ligne $numLigne : $codeFed.$fn = id_adht $idVal";
                $rapport['nb_succes']++;
            }

            if (!empty($maj)) {
                $maj['modifie_par'] = $this->getUser()?->getUserIdentifier();
                $this->connection->update('comitemaore_federations', $maj, ['id_federation' => $idFed]);
            }
        }

        $rapport['resume'] = "Fédérations : {$rapport['nb_succes']} fonction(s) mises à jour, {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // IMPORT SECTIONS — 1 ligne par section
    // ------------------------------------------------------------------
    private function importerSections(string $filepath): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        $this->verifierColonnesRequises($lignes, ['section']);

        foreach ($lignes as $i => $ligne) {
            $numLigne   = $i + 2;
            $nomSection = trim($ligne['section'] ?? '');

            if (empty($nomSection)) {
                $rapport['erreurs'][] = "Ligne $numLigne : colonne 'section' vide.";
                $rapport['nb_erreurs']++;
                continue;
            }

            $sec = $this->connection->fetchAssociative(
                'SELECT id_section FROM comitemaore_sections WHERE section = ?', [$nomSection]
            );
            if (!$sec) {
                $rapport['erreurs'][] = "Ligne $numLigne : section « $nomSection » introuvable dans comitemaore_sections.";
                $rapport['nb_erreurs']++;
                continue;
            }
            $idSection = $sec['id_section'];
            $maj       = [];

            foreach (self::FONCTIONS as $fn) {
                $col   = $fn . '_id';
                $idVal = isset($ligne[$col]) ? (int) trim($ligne[$col]) : 0;

                if ($idVal <= 0) {
                    $rapport['ignores'][] = "Ligne $numLigne ($nomSection) : colonne $col vide — ignorée.";
                    $rapport['nb_ignores']++;
                    continue;
                }

                $err = $this->verifierAdherent($idVal, $numLigne, "$nomSection.$fn");
                if ($err) {
                    $rapport['erreurs'][] = $err;
                    $rapport['nb_erreurs']++;
                    continue;
                }

                $maj[$fn] = $idVal;
                $rapport['succes'][] = "Ligne $numLigne : $nomSection.$fn = id_adht $idVal";
                $rapport['nb_succes']++;
            }

            if (!empty($maj)) {
                $maj['modifie_par'] = $this->getUser()?->getUserIdentifier();
                $this->connection->update('comitemaore_sections', $maj, ['id_section' => $idSection]);
            }
        }

        $rapport['resume'] = "Sections : {$rapport['nb_succes']} fonction(s) mises à jour, {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // Vérifier qu'un id_adht existe bien dans comitemaore_adherent
    // ------------------------------------------------------------------
    private function verifierAdherent(int $idAdht, int $numLigne, string $contexte): ?string
    {
        $adht = $this->connection->fetchAssociative(
            'SELECT id_adht, nom_adht, prenom_adht, NIN_adh FROM comitemaore_adherent WHERE id_adht = ?',
            [$idAdht]
        );
        if (!$adht) {
            return "Ligne $numLigne ($contexte) : id_adht=$idAdht introuvable dans comitemaore_adherent.";
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function parseCsv(string $filepath): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) throw new \RuntimeException('Impossible de lire le fichier CSV.');

        $firstLine = fgets($handle);
        rewind($handle);
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $headers = fgetcsv($handle, 0, $sep);
        if (!$headers) throw new \RuntimeException('En-tête CSV manquant.');

        $headers = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $headers);

        $lignes = [];
        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (count(array_filter($row)) === 0) continue;
            $lignes[] = array_combine($headers, array_pad($row, count($headers), ''));
        }
        fclose($handle);
        return $lignes;
    }

    private function verifierColonnesRequises(array $lignes, array $requises): void
    {
        if (empty($lignes)) throw new \RuntimeException('Fichier CSV vide.');
        $manquantes = array_diff($requises, array_keys($lignes[0]));
        if (!empty($manquantes)) {
            throw new \RuntimeException('Colonnes manquantes : ' . implode(', ', $manquantes));
        }
    }

    private function initRapport(): array
    {
        return [
            'nb_succes'  => 0, 'nb_erreurs' => 0,
            'nb_ignores' => 0, 'succes'     => [],
            'erreurs'    => [], 'ignores'   => [],
            'resume'     => '',
        ];
    }
}
