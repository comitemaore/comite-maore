<?php

namespace App\Controller;

use App\Service\AdminResolver;
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
    public function __construct(
        private readonly Connection  $connection,
        private readonly LogService  $logService,
        private readonly AdminResolver  $adminResolver,
    ) {}

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function currentAdherent(): ?array
    {
        $login = $this->getUser()?->getUserIdentifier();
        if (!$login) return null;
        return $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null;
    }

    private function log(string $action, string $resultat, ?int $idCible, array $detail = []): void
    {
        $adht = $this->currentAdherent();
        $this->logService->log($action, $resultat, 'adherent', $idCible, $detail,
            $adht['id_adht'] ?? null, $adht['login_adht'] ?? null);
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
            ->select('a.*')
            ->from('comitemaore_adherent', 'a')
            ->orderBy('a.nom_adht', 'ASC');

        // Un non-admin ne voit que sa section
        if (!$isAdmin && $adht) {
            $qb->andWhere('a.id_section = :sec')
               ->setParameter('sec', $adht['id_section']);
        }

        if ($search) {
            $qb->andWhere('a.nom_adht LIKE :q OR a.prenom_adht LIKE :q OR a.email_adht LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $adherents = $qb->fetchAllAssociative();
        $this->log('list_adherents', 'info', null, ['search' => $search]);

        return $this->render('adherent/list.html.twig', [
            'adherents' => $adherents,
            'search'    => $search,
            'is_admin'  => $isAdmin,
            'current'   => $adht,
        ]);
    }

    // ------------------------------------------------------------------
    // VOIR
    // ------------------------------------------------------------------
    #[Route('/{id}', name: 'app_adherent_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$id]
        );
        if (!$adherent) throw $this->createNotFoundException("Adhérent #$id introuvable.");

        $this->log('view_adherent', 'info', $id);

        return $this->render('adherent/show.html.twig', [
            'adherent' => $adherent,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $this->currentAdherent(),
        ]);
    }

    // ------------------------------------------------------------------
    // CRÉER — step 1 : formulaire
    // ------------------------------------------------------------------
    #[Route('/nouveau', name: 'app_adherent_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $adht  = $this->currentAdherent();
        $error = null;

        if ($request->isMethod('POST')) {
            $data = $this->extractFormData($request);

            // Vérification : même section
            if (!$this->isGranted('ROLE_ADMIN')) {
                $data['id_section'] = $adht['id_section'];
            }

            // Créer une demande d'approbation
            $idApprobation = $this->creerDemandeApprobation($adht, 'ajout', $data);
            $this->log('demande_ajout_adherent', 'info', null, ['id_approbation' => $idApprobation]);
            $this->addFlash('success', "Demande d'ajout créée (#$idApprobation). En attente de 2 autres approbations admin.");

            return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
        }

        $sections = $this->connection->fetchAllAssociative('SELECT * FROM comitemaore_sections ORDER BY section');

        return $this->render('adherent/form.html.twig', [
            'adherent'  => [],
            'sections'  => $sections,
            'mode'      => 'nouveau',
            'is_admin'  => $this->isGranted('ROLE_ADMIN'),
            'current'   => $adht,
            'error'     => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER
    // ------------------------------------------------------------------
    #[Route('/{id}/modifier', name: 'app_adherent_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$id]
        );
        if (!$adherent) throw $this->createNotFoundException("Adhérent #$id introuvable.");

        $adht = $this->currentAdherent();
        $this->checkSectionAccess($adherent, $adht);

        if ($request->isMethod('POST')) {
            $data = $this->extractFormData($request);
            $data['id_adht'] = $id;

            $idApprobation = $this->creerDemandeApprobation($adht, 'modification', $data);
            $this->log('demande_modif_adherent', 'info', $id, ['id_approbation' => $idApprobation]);
            $this->addFlash('success', "Demande de modification créée (#$idApprobation). En attente d'approbation.");

            return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
        }

        $sections = $this->connection->fetchAllAssociative('SELECT * FROM comitemaore_sections ORDER BY section');

        return $this->render('adherent/form.html.twig', [
            'adherent' => $adherent,
            'sections' => $sections,
            'mode'     => 'modifier',
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $adht,
            'error'    => null,
        ]);
    }

    // ------------------------------------------------------------------
    // SUPPRIMER
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

        $idApprobation = $this->creerDemandeApprobation($adht, 'suppression', ['id_adht' => $id]);
        $this->log('demande_suppression_adherent', 'info', $id, ['id_approbation' => $idApprobation]);
        $this->addFlash('warning', "Demande de suppression créée (#$idApprobation). En attente d'approbation.");

        return $this->redirectToRoute('app_approbation_show', ['id' => $idApprobation]);
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function extractFormData(Request $request): array
    {
        return [
            'civilite_adht'          => $request->request->get('civilite_adht', ''),
            'prenom_adht'            => $request->request->get('prenom_adht', ''),
            'nom_adht'               => $request->request->get('nom_adht', ''),
            'adresse_adht'           => $request->request->get('adresse_adht', ''),
            'cp_adht'                => $request->request->get('cp_adht', ''),
            'ville_adht'             => $request->request->get('ville_adht', ''),
            'telephonef_adht'        => $request->request->get('telephonef_adht', ''),
            'telephonep_adht'        => $request->request->get('telephonep_adht', ''),
            'email_adht'             => $request->request->get('email_adht', ''),
            'profession_adht'        => $request->request->get('profession_adht', ''),
            'promotion_adht'         => $request->request->get('promotion_adht', ''),
            'datenaisance_adht'      => $request->request->get('datenaisance_adht', '') ?: null,
            'id_section'             => $request->request->getInt('id_section') ?: null,
            'visibl_adht'            => $request->request->get('visibl_adht', 'Non'),
            'disponib_adht'          => $request->request->get('disponib_adht', ''),
            'autres_info_adht'       => $request->request->get('autres_info_adht', ''),
            'cotis_adht'             => $request->request->get('cotis_adht', 'Non'),
            'date_echeance_cotis'    => $request->request->get('date_echeance_cotis', '') ?: null,
        ];
    }

    private function checkSectionAccess(array $adherent, ?array $currentUser): void
    {
        if ($this->isGranted('ROLE_ADMIN')) return;
        if (!$currentUser || $currentUser['id_section'] !== $adherent['id_section']) {
            throw $this->createAccessDeniedException("Vous ne pouvez modifier que les adhérents de votre section.");
        }
    }

    private function creerDemandeApprobation(array $initiateur, string $typeOp, array $data): int
    {
        $expiration = (new \DateTime())->modify('+48 hours')->format('Y-m-d H:i:s');
        $this->connection->insert('comitemaore_approbation', [
            'id_adht_cible'    => $data['id_adht'] ?? 0,
            'type_operation'   => $typeOp,
            'data_operation'   => json_encode($data, JSON_UNESCAPED_UNICODE),
            'statut'           => 'en_attente',
            'id_initiateur'    => $initiateur['id_adht'],
            'id_section_init'  => $initiateur['id_section'],
            'date_creation'    => (new \DateTime())->format('Y-m-d H:i:s'),
            'date_expiration'  => $expiration,
        ]);

        $idApprobation = (int) $this->connection->lastInsertId();

        // Vote automatique de l'initiateur
        $this->connection->insert('comitemaore_approbation_vote', [
            'id_approbation'    => $idApprobation,
            'id_votant'         => $initiateur['id_adht'],
            'id_section_votant' => $initiateur['id_section'],
            'role_vote'         => 'initiateur',
            'decision'          => 'approuve',
            'date_vote'         => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $idApprobation;
    }
}
