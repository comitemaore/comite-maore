<?php

namespace App\Controller;

use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/document')]
class DocumentController extends AbstractController
{
    private const UPLOAD_DIR     = 'uploads/documents';
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_MIME   = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        private readonly Connection  $connection,
        private readonly LogService  $logService,
        private readonly string      $projectDir,
    ) {}

    #[Route('/{idAdht}', name: 'app_document_list', methods: ['GET'], requirements: ['idAdht' => '\d+'])]
    public function list(int $idAdht): Response
    {
        $adherent  = $this->getAdherent($idAdht);
        $documents = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_document WHERE id_adht = ? ORDER BY date_upload DESC', [$idAdht]
        );
        $current = $this->currentAdherent();
        $this->logService->log('list_documents', 'info', 'document', $idAdht, [], $current['id_adht'] ?? null, $current['login_adht'] ?? null);

        return $this->render('document/list.html.twig', [
            'adherent'  => $adherent,
            'documents' => $documents,
            'is_admin'  => $this->isGranted('ROLE_ADMIN'),
            'current'   => $current,
        ]);
    }

    #[Route('/{idAdht}/upload', name: 'app_document_upload', methods: ['GET', 'POST'], requirements: ['idAdht' => '\d+'])]
    public function upload(int $idAdht, Request $request): Response
    {
        $adherent = $this->getAdherent($idAdht);
        $current  = $this->currentAdherent();
        $error    = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('fichier');

            if (!$file) {
                $error = 'Aucun fichier sélectionné.';
            } elseif ($file->getSize() > self::MAX_SIZE_BYTES) {
                $error = 'Fichier trop volumineux (max 10 Mo).';
            } elseif (!in_array($file->getMimeType(), self::ALLOWED_MIME)) {
                $error = 'Type de fichier non autorisé.';
            } else {
                $nomOriginal = $file->getClientOriginalName();
                $nomFichier  = uniqid('doc_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nomOriginal);
                $dossier     = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . $idAdht;

                if (!is_dir($dossier)) mkdir($dossier, 0775, true);
                $file->move($dossier, $nomFichier);

                $this->connection->insert('comitemaore_document', [
                    'id_adht'        => $idAdht,
                    'titre_doc'      => $request->request->get('titre_doc', $nomOriginal),
                    'type_doc'       => $request->request->get('type_doc', ''),
                    'nom_fichier'    => $nomOriginal,
                    'chemin_fichier' => self::UPLOAD_DIR . '/' . $idAdht . '/' . $nomFichier,
                    'mime_type'      => $file->getMimeType(),
                    'taille_fichier' => $file->getSize(),
                    'description'    => $request->request->get('description', ''),
                    'date_upload'    => (new \DateTime())->format('Y-m-d H:i:s'),
                    'uploade_par'    => $current['id_adht'] ?? null,
                ]);

                $this->logService->log('upload_document', 'succes', 'document', $idAdht, ['fichier' => $nomOriginal], $current['id_adht'] ?? null, $current['login_adht'] ?? null);
                $this->addFlash('success', 'Document uploadé avec succès.');
                return $this->redirectToRoute('app_document_list', ['idAdht' => $idAdht]);
            }
        }

        return $this->render('document/upload.html.twig', [
            'adherent' => $adherent,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $current,
            'error'    => $error,
        ]);
    }

    #[Route('/{idDoc}/supprimer', name: 'app_document_delete', methods: ['POST'], requirements: ['idDoc' => '\d+'])]
    public function delete(int $idDoc, Request $request): Response
    {
        $doc = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_document WHERE id_doc = ?', [$idDoc]
        );
        if (!$doc) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('del_doc_' . $idDoc, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $chemin = $this->projectDir . '/public/' . $doc['chemin_fichier'];
        if (file_exists($chemin)) unlink($chemin);

        $this->connection->delete('comitemaore_document', ['id_doc' => $idDoc]);
        $current = $this->currentAdherent();
        $this->logService->log('delete_document', 'succes', 'document', $doc['id_adht'], ['id_doc' => $idDoc], $current['id_adht'] ?? null, $current['login_adht'] ?? null);

        $this->addFlash('success', 'Document supprimé.');
        return $this->redirectToRoute('app_document_list', ['idAdht' => $doc['id_adht']]);
    }

    private function getAdherent(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$id]
        );
        if (!$row) throw $this->createNotFoundException();
        return $row;
    }

    private function currentAdherent(): ?array
    {
        $login = $this->getUser()?->getUserIdentifier();
        return $login ? ($this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null) : null;
    }

    private function getFileUrl(array $doc): string
{
    if ($doc['type_doc'] === 'photo_identite') {
        // récupérer NIN via adhérent
        $nin = $this->connection->fetchOne(
            'SELECT NIN_adh FROM comitemaore_adherent WHERE id_adht = ?',
            [$doc['id_adht']]
        );

        return '/adherent/photo/' . urlencode($nin);
    }

    return '/' . ltrim($doc['chemin_fichier'], '/');
}
}
