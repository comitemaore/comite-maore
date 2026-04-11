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
    // Fonctions valides communes aux 3 niveaux
    private const FONCTIONS = [
        'president', 'vice_president', 'secretaire', 'tresorier',
        'secretaire_adjoint', 'tresorier_adjoint',
        'administrateur1', 'administrateur2', 'administrateur3',
    ];

    // Colonnes CSV obligatoires selon le type
    private const COLONNES_REQUISES = [
        'bureau'      => ['fonction', 'prenom', 'nom', 'email'],
        'federations' => ['federation', 'fonction', 'prenom', 'nom', 'email'],
        'sections'    => ['section', 'fonction', 'prenom', 'nom', 'email'],
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
    // TRAITEMENT D'UN IMPORT CSV
    // ------------------------------------------------------------------
    #[Route('/process/{type}', name: 'app_import_process', methods: ['POST'])]
    public function process(string $type, Request $request): Response
    {
        if (!in_array($type, ['bureau', 'federations', 'sections'], true)) {
            throw $this->createNotFoundException("Type d'import inconnu : $type");
        }

        if (!$this->isCsrfTokenValid('import_' . $type, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $file = $request->files->get('csv_file');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_import');
        }

        if (!in_array($file->getClientOriginalExtension(), ['csv', 'CSV'], true)) {
            $this->addFlash('danger', 'Seuls les fichiers .csv sont acceptés.');
            return $this->redirectToRoute('app_import');
        }

        $mode = $request->request->get('mode', 'insert'); // insert | update | upsert

        try {
            $rapport = match ($type) {
                'bureau'      => $this->importerBureau($file->getPathname(), $mode),
                'federations' => $this->importerFederations($file->getPathname(), $mode),
                'sections'    => $this->importerSections($file->getPathname(), $mode),
            };

            $this->logService->log("import_csv_{$type}", 'succes', 'import', null, [
                'fichier' => $file->getClientOriginalName(),
                'mode'    => $mode,
                'rapport' => $rapport['resume'],
            ], null, $this->getUser()?->getUserIdentifier());

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'import : ' . $e->getMessage());
            return $this->redirectToRoute('app_import');
        }

        return $this->render('import/rapport.html.twig', [
            'rapport' => $rapport,
            'type'    => $type,
            'fichier' => $file->getClientOriginalName(),
            'mode'    => $mode,
        ]);
    }

    // ------------------------------------------------------------------
    // IMPORT BUREAU EXÉCUTIF NATIONAL
    // ------------------------------------------------------------------
    private function importerBureau(string $filepath, string $mode): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        $this->verifierColonnes($lignes, self::COLONNES_REQUISES['bureau']);

        // Récupérer ou créer le bureau de l'année courante
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
            $fonction = strtolower(trim($ligne['fonction'] ?? ''));

            if (!in_array($fonction, self::FONCTIONS, true)) {
                $rapport['erreurs'][] = "Ligne $numLigne : fonction « $fonction » invalide.";
                $rapport['nb_erreurs']++;
                continue;
            }

            $idAdht = $this->upsertAdherent($ligne, null, $mode, $rapport, $numLigne);
            if ($idAdht === null) continue;

            // Mettre à jour la colonne de fonction dans comitemaore_burexecnat
            $this->connection->update('comitemaore_burexecnat',
                [$fonction => $idAdht],
                ['id_burexec' => $idBureau]
            );
            $rapport['succes'][] = "Ligne $numLigne : {$ligne['prenom']} {$ligne['nom']} → bureau.$fonction";
            $rapport['nb_succes']++;
        }

        $rapport['resume'] = "Bureau : {$rapport['nb_succes']} importé(s), {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // IMPORT FÉDÉRATIONS
    // ------------------------------------------------------------------
    private function importerFederations(string $filepath, string $mode): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        $this->verifierColonnes($lignes, self::COLONNES_REQUISES['federations']);

        foreach ($lignes as $i => $ligne) {
            $numLigne    = $i + 2;
            $codeFed     = trim($ligne['federation'] ?? '');
            $nomComplet  = trim($ligne['nom_complet'] ?? $codeFed);
            $fonction    = strtolower(trim($ligne['fonction'] ?? ''));

            if (empty($codeFed)) {
                $rapport['erreurs'][] = "Ligne $numLigne : colonne 'federation' vide.";
                $rapport['nb_erreurs']++;
                continue;
            }
            if (!in_array($fonction, self::FONCTIONS, true)) {
                $rapport['erreurs'][] = "Ligne $numLigne : fonction « $fonction » invalide.";
                $rapport['nb_erreurs']++;
                continue;
            }

            // Trouver ou créer la fédération
            $fed = $this->connection->fetchAssociative(
                'SELECT * FROM comitemaore_federations WHERE federation = ?', [$codeFed]
            );
            if (!$fed) {
                $this->connection->insert('comitemaore_federations', [
                    'federation' => $codeFed,
                    'nom_complet'=> $nomComplet ?: $codeFed,
                ]);
                $idFed = (int) $this->connection->lastInsertId();
                $rapport['crees'][] = "Fédération « $codeFed » créée.";
            } else {
                $idFed = $fed['id_federation'];
                if ($nomComplet && empty($fed['nom_complet'])) {
                    $this->connection->update('comitemaore_federations',
                        ['nom_complet' => $nomComplet], ['id_federation' => $idFed]);
                }
            }

            // Trouver la section de l'adhérent (optionnel dans le CSV fédérations)
            $idSection = $this->trouverSection($ligne['section'] ?? null, $idFed);

            $idAdht = $this->upsertAdherent($ligne, $idSection, $mode, $rapport, $numLigne);
            if ($idAdht === null) continue;

            $this->connection->update('comitemaore_federations',
                [$fonction => $idAdht],
                ['id_federation' => $idFed]
            );
            $rapport['succes'][] = "Ligne $numLigne : {$ligne['prenom']} {$ligne['nom']} → fed.$codeFed.$fonction";
            $rapport['nb_succes']++;
        }

        $rapport['resume'] = "Fédérations : {$rapport['nb_succes']} importé(s), {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // IMPORT SECTIONS
    // ------------------------------------------------------------------
    private function importerSections(string $filepath, string $mode): array
    {
        $lignes  = $this->parseCsv($filepath);
        $rapport = $this->initRapport();

        $this->verifierColonnes($lignes, self::COLONNES_REQUISES['sections']);

        foreach ($lignes as $i => $ligne) {
            $numLigne   = $i + 2;
            $nomSection = trim($ligne['section'] ?? '');
            $codeFed    = trim($ligne['federation'] ?? '');
            $fonction   = strtolower(trim($ligne['fonction'] ?? ''));

            if (empty($nomSection)) {
                $rapport['erreurs'][] = "Ligne $numLigne : colonne 'section' vide.";
                $rapport['nb_erreurs']++;
                continue;
            }
            if (!in_array($fonction, self::FONCTIONS, true)) {
                $rapport['erreurs'][] = "Ligne $numLigne : fonction « $fonction » invalide.";
                $rapport['nb_erreurs']++;
                continue;
            }

            // Résoudre la fédération
            $idFederation = null;
            if ($codeFed) {
                $fed = $this->connection->fetchAssociative(
                    'SELECT id_federation FROM comitemaore_federations WHERE federation = ?', [$codeFed]
                );
                $idFederation = $fed ? $fed['id_federation'] : null;
            }

            // Trouver ou créer la section
            $section = $this->connection->fetchAssociative(
                'SELECT * FROM comitemaore_sections WHERE section = ?', [$nomSection]
            );
            if (!$section) {
                $this->connection->insert('comitemaore_sections', [
                    'section'      => $nomSection,
                    'federation'   => $codeFed ?: '',
                    'id_federation'=> $idFederation ?? 0,
                ]);
                $idSection = (int) $this->connection->lastInsertId();
                $rapport['crees'][] = "Section « $nomSection » créée.";
            } else {
                $idSection = $section['id_section'];
            }

            $idAdht = $this->upsertAdherent($ligne, $idSection, $mode, $rapport, $numLigne);
            if ($idAdht === null) continue;

            $this->connection->update('comitemaore_sections',
                [$fonction => $idAdht],
                ['id_section' => $idSection]
            );
            $rapport['succes'][] = "Ligne $numLigne : {$ligne['prenom']} {$ligne['nom']} → section.$nomSection.$fonction";
            $rapport['nb_succes']++;
        }

        $rapport['resume'] = "Sections : {$rapport['nb_succes']} importé(s), {$rapport['nb_erreurs']} erreur(s).";
        return $rapport;
    }

    // ------------------------------------------------------------------
    // Insérer ou mettre à jour un adhérent
    // Retourne l'id_adht ou null en cas d'erreur
    // ------------------------------------------------------------------
    private function upsertAdherent(array $ligne, ?int $idSection, string $mode, array &$rapport, int $numLigne): ?int
    {
        $email  = trim($ligne['email'] ?? '');
        $prenom = trim($ligne['prenom'] ?? '');
        $nom    = strtoupper(trim($ligne['nom'] ?? ''));

        if (empty($prenom) || empty($nom)) {
            $rapport['erreurs'][] = "Ligne $numLigne : prénom ou nom manquant.";
            $rapport['nb_erreurs']++;
            return null;
        }

        // Chercher l'adhérent existant par email ou par nom+prénom
        $existing = null;
        if ($email) {
            $existing = $this->connection->fetchAssociative(
                'SELECT id_adht FROM comitemaore_adherent WHERE email_adht = ?', [$email]
            ) ?: null;
        }
        if (!$existing) {
            $existing = $this->connection->fetchAssociative(
                'SELECT id_adht FROM comitemaore_adherent WHERE nom_adht = ? AND prenom_adht = ?',
                [$nom, $prenom]
            ) ?: null;
        }

        $donnees = [
            'prenom_adht'      => $prenom,
            'nom_adht'         => $nom,
            'email_adht'       => $email ?: null,
            'telephonep_adht'  => trim($ligne['telephone'] ?? '') ?: null,
            'profession_adht'  => trim($ligne['profession'] ?? '') ?: null,
            'adresse_adht'     => trim($ligne['adresse'] ?? '') ?: null,
            'ville_adht'       => trim($ligne['ville'] ?? '') ?: null,
            'cp_adht'          => trim($ligne['cp'] ?? '') ?: null,
            'id_section'       => $idSection,
            'datemodiffiche_adht' => (new \DateTime())->format('Y-m-d'),
        ];

        if ($existing) {
            if ($mode === 'insert') {
                // Ne pas toucher l'existant en mode insert pur
                $rapport['ignores'][] = "Ligne $numLigne : $prenom $nom déjà existant (ignoré en mode insert).";
                $rapport['nb_ignores']++;
                return (int) $existing['id_adht'];
            }
            // update ou upsert
            $this->connection->update('comitemaore_adherent', $donnees, ['id_adht' => $existing['id_adht']]);
            $rapport['nb_maj']++;
            return (int) $existing['id_adht'];
        }

        // Création
        $donnees['datecreationfiche_adht'] = (new \DateTime())->format('Y-m-d');
        $donnees['cotis_adht'] = 'Non';
        $donnees['visibl_adht'] = 'Non';
        $this->connection->insert('comitemaore_adherent', $donnees);
        $rapport['nb_crees']++;
        return (int) $this->connection->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function parseCsv(string $filepath): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) throw new \RuntimeException('Impossible de lire le fichier CSV.');

        // Détecter le séparateur (virgule ou point-virgule)
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        // Lire l'en-tête
        $headers = fgetcsv($handle, 0, $sep);
        if (!$headers) throw new \RuntimeException('Fichier CSV vide ou en-tête manquant.');

        // Nettoyer les en-têtes (BOM UTF-8, espaces)
        $headers = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $headers);

        $lignes = [];
        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (count(array_filter($row)) === 0) continue; // Ligne vide
            $ligne = array_combine($headers, array_pad($row, count($headers), ''));
            $lignes[] = $ligne;
        }
        fclose($handle);

        return $lignes;
    }

    private function verifierColonnes(array $lignes, array $requises): void
    {
        if (empty($lignes)) throw new \RuntimeException('Le fichier CSV est vide (aucune donnée après l\'en-tête).');
        $colonnes = array_keys($lignes[0]);
        $manquantes = array_diff($requises, $colonnes);
        if (!empty($manquantes)) {
            throw new \RuntimeException(
                'Colonnes manquantes dans le CSV : ' . implode(', ', $manquantes)
                . '. Colonnes trouvées : ' . implode(', ', $colonnes)
            );
        }
    }

    private function trouverSection(?string $nomSection, int $idFederation): ?int
    {
        if (!$nomSection) return null;
        $sec = $this->connection->fetchAssociative(
            'SELECT id_section FROM comitemaore_sections WHERE section = ?', [trim($nomSection)]
        );
        return $sec ? (int) $sec['id_section'] : null;
    }

    private function initRapport(): array
    {
        return [
            'nb_succes'  => 0,
            'nb_erreurs' => 0,
            'nb_crees'   => 0,
            'nb_maj'     => 0,
            'nb_ignores' => 0,
            'succes'     => [],
            'erreurs'    => [],
            'ignores'    => [],
            'crees'      => [],
            'resume'     => '',
        ];
    }
}
