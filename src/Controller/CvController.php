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
#[Route('/cv')]
class CvController extends AbstractController
{
    public function __construct(
        private readonly Connection  $connection,
        private readonly LogService  $logService,
    ) {}

    #[Route('/{idAdht}', name: 'app_cv_show', methods: ['GET'], requirements: ['idAdht' => '\d+'])]
    public function show(int $idAdht): Response
    {
        $adherent = $this->getAdherent($idAdht);
        $entrees  = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_cv WHERE id_adht = ? ORDER BY type_entree, periode_debut DESC', [$idAdht]
        );
        $current = $this->currentAdherent();
        $this->logService->log('view_cv', 'info', 'cv', $idAdht, [], $current['id_adht'] ?? null, $current['login_adht'] ?? null);

        return $this->render('cv/show.html.twig', [
            'adherent' => $adherent,
            'entrees'  => $entrees,
            'grouped'  => $this->groupByType($entrees),
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $current,
        ]);
    }

    #[Route('/{idAdht}/ajouter', name: 'app_cv_add', methods: ['GET', 'POST'], requirements: ['idAdht' => '\d+'])]
    public function add(int $idAdht, Request $request): Response
    {
        $adherent = $this->getAdherent($idAdht);
        $current  = $this->currentAdherent();

        if ($request->isMethod('POST')) {
            $this->connection->insert('comitemaore_cv', [
                'id_adht'             => $idAdht,
                'type_entree'         => $request->request->get('type_entree', 'etude'),
                'intitule'            => $request->request->get('intitule', ''),
                'etablissement'       => $request->request->get('etablissement', ''),
                'periode_debut'       => $request->request->get('periode_debut', ''),
                'periode_fin'         => $request->request->get('periode_fin', ''),
                'mention'             => $request->request->get('mention', ''),
                'notes_particulieres' => $request->request->get('notes_particulieres', ''),
                'ordre_affichage'     => $request->request->getInt('ordre_affichage'),
                'date_creation'       => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->logService->log('add_cv', 'succes', 'cv', $idAdht, [], $current['id_adht'] ?? null, $current['login_adht'] ?? null);
            $this->addFlash('success', 'Entrée CV ajoutée.');
            return $this->redirectToRoute('app_cv_show', ['idAdht' => $idAdht]);
        }

        return $this->render('cv/form.html.twig', [
            'adherent' => $adherent,
            'entree'   => [],
            'mode'     => 'ajouter',
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $current,
            'error'    => null,
        ]);
    }

    #[Route('/entree/{id}/modifier', name: 'app_cv_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $entree  = $this->connection->fetchAssociative('SELECT * FROM comitemaore_cv WHERE id_cv = ?', [$id]);
        if (!$entree) throw $this->createNotFoundException();
        $current = $this->currentAdherent();

        if ($request->isMethod('POST')) {
            $this->connection->update('comitemaore_cv', [
                'type_entree'         => $request->request->get('type_entree'),
                'intitule'            => $request->request->get('intitule'),
                'etablissement'       => $request->request->get('etablissement'),
                'periode_debut'       => $request->request->get('periode_debut'),
                'periode_fin'         => $request->request->get('periode_fin'),
                'mention'             => $request->request->get('mention'),
                'notes_particulieres' => $request->request->get('notes_particulieres'),
                'ordre_affichage'     => $request->request->getInt('ordre_affichage'),
                'date_modification'   => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id_cv' => $id]);

            $this->addFlash('success', 'Entrée CV modifiée.');
            return $this->redirectToRoute('app_cv_show', ['idAdht' => $entree['id_adht']]);
        }

        return $this->render('cv/form.html.twig', [
            'adherent' => $this->getAdherent($entree['id_adht']),
            'entree'   => $entree,
            'mode'     => 'modifier',
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $current,
            'error'    => null,
        ]);
    }

    #[Route('/entree/{id}/supprimer', name: 'app_cv_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $entree = $this->connection->fetchAssociative('SELECT * FROM comitemaore_cv WHERE id_cv = ?', [$id]);
        if (!$entree) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('del_cv_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->connection->delete('comitemaore_cv', ['id_cv' => $id]);
        $this->addFlash('success', 'Entrée CV supprimée.');
        return $this->redirectToRoute('app_cv_show', ['idAdht' => $entree['id_adht']]);
    }

    private function groupByType(array $entrees): array
    {
        $grouped = ['etude' => [], 'diplome' => [], 'experience' => [], 'note' => []];
        foreach ($entrees as $e) {
            $grouped[$e['type_entree']][] = $e;
        }
        return $grouped;
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
}
