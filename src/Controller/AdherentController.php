<?php

namespace App\Controller;

use App\Service\AdherentFonctionService;
use App\Service\ApprobationService;
use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/adherent')]
class AdherentController extends AbstractController
{
    // Répertoire photos hors racine publique et hors git (.gitignore)
    private const PHOTO_DIR = 'var/photos';

    public function __construct(
        private readonly Connection               $connection,
        private readonly LogService              $logService,
        private readonly ApprobationService      $approbationService,
        private readonly AdherentFonctionService $fonctionService,
        private readonly string                  $projectDir,
    ) {}

    // ------------------------------------------------------------------
    private function currentAdherent(): ?array
    {
        $login = $this->getUser()?->getUserIdentifier();
        if (!$login) return null;
        return $this->connection->fetchAssociative(
            'SELECT a.*, s.section AS section_nom
             FROM comitemaore_adherent a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE a.login_adht = ?',
            [$login]
        ) ?: null;
    }

    private function currentAdherentOrFallback(): array
    {
        $adht = $this->currentAdherent();
        if ($adht !== null) return $adht;
        $first = $this->connection->fetchAssociative(
            'SELECT a.*, s.section AS section_nom
             FROM comitemaore_adherent a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             ORDER BY a.id_adht ASC LIMIT 1'
        );
        if ($first) {
            return array_merge($first, [
                'login_adht' => $this->getUser()?->getUserIdentifier() ?? 'unknown',
            ]);
        }
        return [
            'id_adht'     => 0,
            'login_adht'  => $this->getUser()?->getUserIdentifier() ?? 'unknown',
            'id_section'  => null,
            'section_nom' => null,
            'prenom_adht' => '',
            'nom_adht'    => '',
        ];
    }

    private function log(string $action, string $resultat, ?int $idCible, array $detail = []): void
    {
        $login = $this->getUser()?->getUserIdentifier();
        $adht  = $this->currentAdherent();
        $this->logService->log($action, $resultat, 'adherent', $idCible, $detail,
            $adht['id_adht'] ?? null, $login);
    }

    // ------------------------------------------------------------------
    // LISTE
    // ------------------------------------------------------------------
    #[Route('', name: 'app_adherent_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $adht    = $this->currentAdherent();
        $search  = $request->query->get('q', '');
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $qb = $this->connection->createQueryBuilder()
            ->select('a.*', 's.section AS section_nom', 'f.federation AS federation_nom')
            ->from('comitemaore_adherent', 'a')
            ->leftJoin('a', 'comitemaore_sections', 's', 'a.id_section = s.id_section')
            ->leftJoin('s', 'comitemaore_federations', 'f', 's.id_federation = f.id_federation')
            ->orderBy('a.nom_adht', 'ASC');

        if (!$isAdmin && $adht && $adht['id_section']) {
            $qb->andWhere('a.id_section = :sec')
               ->setParameter('sec', $adht['id_section']);
        }
        if ($search) {
            $qb->andWhere('a.nom_adht LIKE :q OR a.prenom_adht LIKE :q OR a.email_adht LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $adherents = $qb->fetchAllAssociative();
        $contextes = $this->fonctionService->resoudreListe($adherents);
        $this->log('list_adherents', 'info', null, ['search' => $search]);

        return $this->render('adherent/list.html.twig', [
            'adherents'     => $adherents,
            'contextes'     => $contextes,
            'search'        => $search,
            'is_admin'      => $isAdmin,
            'current'       => $adht,
            'bypass_actif'  => $this->approbationService->bypassActif(),
            'statut_bypass' => $this->approbationService->statutBypass(),
        ]);
    }

    // ------------------------------------------------------------------
    // VOIR
    // ------------------------------------------------------------------
    #[Route('/{id}', name: 'app_adherent_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT a.*, s.section AS section_nom, f.federation AS federation_nom
             FROM comitemaore_adherent a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             LEFT JOIN comitemaore_federations f ON s.id_federation = f.id_federation
             WHERE a.id_adht = ?',
            [$id]
        );
        if (!$adherent) throw $this->createNotFoundException("Adhérent #$id introuvable.");

        $contexte = $this->fonctionService->resoudre(
            $id, $adherent['id_section'] ? (int)$adherent['id_section'] : null
        );

        // Photo d'identité
        $photoUrl = $this->trouverPhoto($adherent['NIN_adh'] ?? '');

        $this->log('view_adherent', 'info', $id);

        return $this->render('adherent/show.html.twig', [
            'adherent' => $adherent,
            'contexte' => $contexte,
            'photoUrl' => $photoUrl,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $this->currentAdherent(),
        ]);
    }

    // ------------------------------------------------------------------
    // CRÉER
    // ------------------------------------------------------------------
    #[Route('/nouveau', name: 'app_adherent_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $adht        = $this->currentAdherent();
        $bypassActif = $this->approbationService->bypassActif();
        $error       = null;
        $data        = [];

        if ($request->isMethod('POST')) {
            $data = $this->extractFormData($request);

            if (!$this->isGranted('ROLE_ADMIN') && $adht) {
                $data['id_section'] = $adht['id_section'];
            }

            // Validation NIN
            if (empty($data['NIN_adh'])) {
                $error = 'Le NIN est obligatoire.';
                goto render_form_new;
            }

            // NIN doublon
            $existant = $this->connection->fetchAssociative(
                'SELECT id_adht, nom_adht, prenom_adht FROM comitemaore_adherent WHERE NIN_adh = ?',
                [$data['NIN_adh']]
            );
            if ($existant) {
                $lien  = $this->generateUrl('app_adherent_show', ['id' => $existant['id_adht']]);
                $error = sprintf(
                    'Le NIN <strong>%s</strong> est déjà attribué à <strong>%s %s</strong> (id:%d). '
                    . '<a href="%s" style="color:inherit;text-decoration:underline;">Voir la fiche</a>. '
                    . 'Vérifiez lequel est incorrect.',
                    htmlspecialchars($data['NIN_adh']),
                    htmlspecialchars($existant['prenom_adht']),
                    htmlspecialchars($existant['nom_adht']),
                    $existant['id_adht'], $lien
                );
                goto render_form_new;
            }

            if ($bypassActif) {
                try {
                    $data['datecreationfiche_adht'] = (new \DateTime())->format('Y-m-d');
                    $data['cotis_adht']             = $data['cotis_adht'] ?: 'Non';
                    $data['visibl_adht']            = $data['visibl_adht'] ?: 'Non';
                    $this->connection->insert('comitemaore_adherent', $data);
                    $idAdht = (int) $this->connection->lastInsertId();

                    // Upload photo
                    $this->traiterPhotoUpload($request, $data['NIN_adh'], $idAdht);

                    $statut = $this->approbationService->statutBypass();
                    $this->log('ajout_adherent_direct', 'succes', $idAdht, [
                        'bypass' => true, 'sections_completes' => $statut['completes'],
                    ]);
                    $this->addFlash('success', "Adhérent ajouté (#$idAdht) — mode bypass.");
                    return $this->redirectToRoute('app_adherent_show', ['id' => $idAdht]);
                } catch (\Exception $e) {
                    $error = 'Erreur : ' . $e->getMessage();
                    goto render_form_new;
                }
            }

            // Workflow
            $initiateur    = $this->currentAdherentOrFallback();
            $idApprobation = $this->creerDemandeApprobation($initiateur, 'ajout', $data);
            $this->log('demande_ajout_adherent', 'info', null, ['id_approbation' => $idApprobation]);
            $this->addFlash('success', "Demande d'ajout créée (#$idApprobation).");
            return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
        }

        render_form_new:
        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );
        return $this->render('adherent/form.html.twig', [
            'adherent'      => $data,
            'sections'      => $sections,
            'mode'          => 'nouveau',
            'is_admin'      => $this->isGranted('ROLE_ADMIN'),
            'current'       => $adht,
            'error'         => $error,
            'bypass_actif'  => $bypassActif,
            'statut_bypass' => $this->approbationService->statutBypass(),
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER
    // ------------------------------------------------------------------
    #[Route('/{id}/modifier', name: 'app_adherent_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT a.*, s.section AS section_nom FROM comitemaore_adherent a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE a.id_adht = ?', [$id]
        );
        if (!$adherent) throw $this->createNotFoundException();

        $adht = $this->currentAdherent();
        $this->checkSectionAccess($adherent, $adht);

        if ($request->isMethod('POST')) {
            $data            = $this->extractFormData($request);
            $data['id_adht'] = $id;

            // Upload photo si fournie
            if (!empty($data['NIN_adh'])) {
                $this->traiterPhotoUpload($request, $data['NIN_adh'], $id);
            }

            $initiateur    = $this->currentAdherentOrFallback();
            $idApprobation = $this->creerDemandeApprobation($initiateur, 'modification', $data);
            $this->log('demande_modif_adherent', 'info', $id, ['id_approbation' => $idApprobation]);
            $this->addFlash('success', "Demande de modification créée (#$idApprobation).");
            return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
        }

        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );
        return $this->render('adherent/form.html.twig', [
            'adherent'      => $adherent,
            'sections'      => $sections,
            'mode'          => 'modifier',
            'is_admin'      => $this->isGranted('ROLE_ADMIN'),
            'current'       => $adht,
            'error'         => null,
            'bypass_actif'  => false,
            'statut_bypass' => $this->approbationService->statutBypass(),
        ]);
    }

    // ------------------------------------------------------------------
    // SUPPRIMER — bypass ou workflow + log dédié + nettoyage tables liées
    // ------------------------------------------------------------------
    #[Route('/{id}/supprimer', name: 'app_adherent_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$id]
        );
        if (!$adherent) throw $this->createNotFoundException();

        $adht = $this->currentAdherent();
        $this->checkSectionAccess($adherent, $adht);

        if (!$this->isCsrfTokenValid('delete_adherent_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $bypassActif = $this->approbationService->bypassActif();

        if ($bypassActif) {
            // Log dédié AVANT suppression (pour garder les infos)
            $this->logSuppression($adherent);

            // Nettoyage de toutes les tables liées
            $this->nettoyerTablesLiees($id, $adherent['NIN_adh'] ?? '');

            // Suppression de l'adhérent
            $this->connection->delete('comitemaore_adherent', ['id_adht' => $id]);

            $statut = $this->approbationService->statutBypass();
            $this->addFlash('success',
                "Adhérent {$adherent['prenom_adht']} {$adherent['nom_adht']} supprimé "
                . "— mode bypass ({$statut['completes']}/20 sections)."
            );
            return $this->redirectToRoute('app_adherent_list');
        }

        // Workflow
        $initiateur    = $this->currentAdherentOrFallback();
        $idApprobation = $this->creerDemandeApprobation($initiateur, 'suppression', ['id_adht' => $id]);
        $this->log('demande_suppression_adherent', 'info', $id, ['id_approbation' => $idApprobation]);
        $this->addFlash('warning', "Demande de suppression créée (#$idApprobation).");
        return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function extractFormData(Request $request): array
    {
        //$idSection = $request->request->get('id_section');
        return [
            'NIN_adh'             => strtoupper(trim($request->request->get('NIN_adh', ''))),
            'civilite_adht'       => $request->request->get('civilite_adht', ''),
            'prenom_adht'         => $request->request->get('prenom_adht', ''),
            'nom_adht'            => $request->request->get('nom_adht', ''),
            'adresse_adht'        => $request->request->get('adresse_adht', ''),
            'cp_adht'             => $request->request->get('cp_adht', ''),
            'ville_adht'          => $request->request->get('ville_adht', ''),
            'telephonef_adht'     => $request->request->get('telephonef_adht', ''),
            'telephonep_adht'     => $request->request->get('telephonep_adht', ''),
            'email_adht'          => $request->request->get('email_adht', ''),
            'profession_adht'     => $request->request->get('profession_adht', ''),
            'promotion_adht'      => $request->request->get('promotion_adht', ''),
            'datenaisance_adht'   => $request->request->get('datenaisance_adht', '') ?: null,
            'id_section'          => $request->request->getInt('id_section') ?: null,
            //'id_section'          => is_numeric($idSection) ? (int) $idSection : null,
            'visibl_adht'         => $request->request->get('visibl_adht', 'Non'),
            'disponib_adht'       => $request->request->get('disponib_adht', ''),
            'autres_info_adht'    => $request->request->get('autres_info_adht', ''),
            'cotis_adht'          => $request->request->get('cotis_adht', 'Non'),
            'date_echeance_cotis' => $request->request->get('date_echeance_cotis', '') ?: null,
        ];
    }

    private function traiterPhotoUpload(Request $request, string $nin, int $idAdht): void
    {
        $file = $request->files->get('photo_identite');
        if (!$file || !$file->isValid()) {
            return;
        }

        $mime     = $file->getMimeType();
        $allowed  = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($mime, $allowed)) return;

        $ext     = $mime === 'image/png' ? 'png' : 'jpeg';
        $dir     = $this->projectDir . '/' . self::PHOTO_DIR;
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        // Supprimer l'ancienne photo du même NIN si elle existe
        foreach (['jpeg', 'jpg', 'png'] as $e) {
            $old = $dir . '/' . $nin . '.' . $e;
            if (file_exists($old)) unlink($old);
        }

        $filename = $nin . '.' . $ext;
        $size = $file->getSize();
        $file->move($dir, $filename);
        $fullPath = $dir . '/' . $filename;

        // Fallback si jamais getSize() retourne null ou échoue
        if (!$size && file_exists($fullPath)) {
            $size = filesize($fullPath);
        }

        // Enregistrer dans comitemaore_document
        $chemin = self::PHOTO_DIR . '/' . $filename;
        $login  = $this->getUser()?->getUserIdentifier();

        // Supprimer l'ancien enregistrement photo si existant
        $this->connection->delete('comitemaore_document', [
            'id_adht'  => $idAdht,
            'type_doc' => 'photo_identite'
        ]);

        $this->connection->insert('comitemaore_document', [
            'id_adht'        => $idAdht,
            'titre_doc'      => 'Photo d\'identité',
            'type_doc'       => 'photo_identite',
            'nom_fichier'    => $filename,
            'chemin_fichier' => $chemin,
            'mime_type'      => $mime,
            'taille_fichier' => $size,
            'description'    => 'Photo d\'identité — NIN ' . $nin,
            'date_upload'    => (new \DateTime())->format('Y-m-d H:i:s'),
            'uploade_par'    => $this->connection->fetchOne(
                'SELECT id_adht FROM comitemaore_adherent WHERE login_adht = ?',
                [$login]
            ) ?: null,
        ]);
    }

    private function trouverPhoto(string $nin): ?string
    {
        if (empty($nin)) return null;
        foreach (['jpeg', 'jpg', 'png'] as $ext) {
            $path = $this->projectDir . '/' . self::PHOTO_DIR . '/' . $nin . '.' . $ext;
            if (file_exists($path)) {
                // Servir via une route dédiée
                return '/adherent/photo/' . urlencode($nin);
            }
        }
        return null;
    }

    private function logSuppression(array $adherent): void
    {
        // Log dédié suppression sans les champs photo/document
        $detail = $adherent;
        unset($detail['password_adht']); // Ne pas logger le mot de passe
        $login = $this->getUser()?->getUserIdentifier();
        $this->logService->log(
            'suppression_adherent',
            'succes',
            'adherent',
            $adherent['id_adht'],
            $detail,
            null,
            $login
        );
    }

    private function nettoyerTablesLiees(int $idAdht, string $nin): void
    {
        // Supprimer les fichiers physiques des documents
        $docs = $this->connection->fetchAllAssociative(
            'SELECT chemin_fichier FROM comitemaore_document WHERE id_adht = ?', [$idAdht]
        );
        foreach ($docs as $doc) {
            $path = $this->projectDir . '/public/' . $doc['chemin_fichier'];
            if (file_exists($path)) unlink($path);
        }

        // Supprimer la photo d'identité
        foreach (['jpeg', 'jpg', 'png'] as $ext) {
            $path = $this->projectDir . '/' . self::PHOTO_DIR . '/' . $nin . '.' . $ext;
            if (file_exists($path)) unlink($path);
        }

        // Supprimer les enregistrements liés dans les tables
        $tables = [
            'comitemaore_document'         => 'id_adht',
            'comitemaore_cv'               => 'id_adht',
            'finance_adh'                  => 'id_adht',
            'comitemaore_cotisation_due'   => 'id_adht',
            'comitemaore_log'              => 'id_adht',
            'comitemaore_approbation_vote' => 'id_votant',
        ];
        foreach ($tables as $table => $col) {
            try {
                $this->connection->delete($table, [$col => $idAdht]);
            } catch (\Exception) {}
        }

        // Nullifier les références dans l'organigramme
        $fonctions = [
            'president', 'vice_president', 'secretaire', 'tresorier',
            'secretaire_adjoint', 'tresorier_adjoint',
            'administrateur1', 'administrateur2', 'administrateur3',
        ];
        foreach ($fonctions as $fn) {
            foreach (['comitemaore_sections', 'comitemaore_federations', 'comitemaore_burexecnat'] as $tbl) {
                try {
                    $this->connection->executeStatement(
                        "UPDATE $tbl SET $fn = NULL WHERE $fn = ?", [$idAdht]
                    );
                } catch (\Exception) {}
            }
        }
    }

    // Route pour servir les photos (hors dossier public)
    #[Route('/photo/{nin}', name: 'app_adherent_photo', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function servirPhoto(string $nin): Response
    {
        $nin = preg_replace('/[^A-Z0-9]/', '', strtoupper($nin));
        foreach (['jpeg', 'jpg', 'png'] as $ext) {
            $path = $this->projectDir . '/' . self::PHOTO_DIR . '/' . $nin . '.' . $ext;
            if (file_exists($path)) {
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
                return new Response(
                    file_get_contents($path),
                    200,
                    ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=3600']
                );
            }
        }
        return new Response('', 404);
    }

    private function checkSectionAccess(array $adherent, ?array $currentUser): void
    {
        if ($this->isGranted('ROLE_ADMIN')) return;
        if (!$currentUser || (int)($currentUser['id_section'] ?? 0) !== (int)($adherent['id_section'] ?? -1)) {
            throw $this->createAccessDeniedException(
                "Vous ne pouvez modifier que les adhérents de votre section."
            );
        }
    }

    private function creerDemandeApprobation(array $initiateur, string $typeOp, array $data): int
    {
        $expiration = (new \DateTime())->modify('+48 hours')->format('Y-m-d H:i:s');
        $this->connection->insert('comitemaore_approbation', [
            'id_adht_cible'   => $data['id_adht'] ?? 0,
            'type_operation'  => $typeOp,
            'data_operation'  => json_encode($data, JSON_UNESCAPED_UNICODE),
            'statut'          => 'en_attente',
            'id_initiateur'   => $initiateur['id_adht'],
            'id_section_init' => $initiateur['id_section'] ?? null,
            'date_creation'   => (new \DateTime())->format('Y-m-d H:i:s'),
            'date_expiration' => $expiration,
        ]);
        $idApprobation = (int) $this->connection->lastInsertId();
        $this->connection->insert('comitemaore_approbation_vote', [
            'id_approbation'    => $idApprobation,
            'id_votant'         => $initiateur['id_adht'],
            'id_section_votant' => $initiateur['id_section'] ?? null,
            'role_vote'         => 'initiateur',
            'decision'          => 'approuve',
            'date_vote'         => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
        return $idApprobation;
    }
}
