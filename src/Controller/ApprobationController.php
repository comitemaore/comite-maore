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
#[Route('/approbation')]
class ApprobationController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LogService $logService,
    ) {}

    private function currentAdherent(): ?array
    {
        $login = $this->getUser()?->getUserIdentifier();
        if (!$login) return null;
        return $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null;
    }

    // ------------------------------------------------------------------
    // Liste des demandes en attente
    // ------------------------------------------------------------------
    #[Route('', name: 'app_approbation_list')]
    public function list(): Response
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT app.*,
                    adht.prenom_adht, adht.nom_adht,
                    init.prenom_adht AS init_prenom,
                    init.nom_adht    AS init_nom,
                    (SELECT COUNT(*) FROM comitemaore_approbation_vote v
                     WHERE v.id_approbation = app.id_approbation AND v.decision = 'approuve') AS nb_approuve,
                    (SELECT COUNT(*) FROM comitemaore_approbation_vote v
                     WHERE v.id_approbation = app.id_approbation) AS nb_votes
             FROM comitemaore_approbation app
             LEFT JOIN comitemaore_adherent adht ON app.id_adht_cible = adht.id_adht
             LEFT JOIN comitemaore_adherent init ON app.id_initiateur = init.id_adht
             WHERE app.statut = 'en_attente'
               AND (app.date_expiration IS NULL OR app.date_expiration > NOW())
             ORDER BY app.date_creation DESC"
        );

        return $this->render('approbation/list.html.twig', [
            'demandes' => $rows,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'current'  => $this->currentAdherent(),
        ]);
    }

    // ------------------------------------------------------------------
    // Détail d'une demande
    // ------------------------------------------------------------------
    #[Route('/{id}', name: 'app_approbation_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $currentAdht = $this->currentAdherent();

        $approbation = $this->connection->fetchAssociative(
            "SELECT app.*,
                    adht.prenom_adht, adht.nom_adht,
                    init.prenom_adht AS init_prenom,
                    init.nom_adht    AS init_nom,
                    init.id_section  AS init_section
             FROM comitemaore_approbation app
             LEFT JOIN comitemaore_adherent adht ON app.id_adht_cible = adht.id_adht
             LEFT JOIN comitemaore_adherent init ON app.id_initiateur = init.id_adht
             WHERE app.id_approbation = ?", [$id]
        );
        if (!$approbation) throw $this->createNotFoundException();

        $votes = $this->connection->fetchAllAssociative(
            "SELECT v.*, a.prenom_adht, a.nom_adht, a.id_section
             FROM comitemaore_approbation_vote v
             LEFT JOIN comitemaore_adherent a ON v.id_votant = a.id_adht
             WHERE v.id_approbation = ?
             ORDER BY v.date_vote", [$id]
        );

        $data       = json_decode($approbation['data_operation'] ?? '{}', true);
        $dejaVote   = $currentAdht && in_array($currentAdht['id_adht'], array_column($votes, 'id_votant'));
        $nbApprouve = count(array_filter($votes, fn($v) => $v['decision'] === 'approuve'));

        return $this->render('approbation/show.html.twig', [
            'approbation' => $approbation,
            'votes'       => $votes,
            'data'        => $data,
            'deja_vote'   => $dejaVote,
            'nb_approuve' => $nbApprouve,
            'is_admin'    => $this->isGranted('ROLE_ADMIN'),
            'current'     => $currentAdht,
        ]);
    }

    // ------------------------------------------------------------------
    // Voter directement depuis l'appli (sans token mail)
    // ------------------------------------------------------------------
    #[Route('/{id}/voter', name: 'app_approbation_voter', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function voter(int $id, Request $request): Response
    {
        $currentAdht = $this->currentAdherent();
        if (!$currentAdht) {
            $this->addFlash('danger', 'Votre compte n\'est pas lié à un adhérent.');
            return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
        }

        $approbation = $this->connection->fetchAssociative(
            "SELECT * FROM comitemaore_approbation WHERE id_approbation = ? AND statut = 'en_attente'",
            [$id]
        );
        if (!$approbation) {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');
            return $this->redirectToRoute('app_approbation_list');
        }

        $dejaVote = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comitemaore_approbation_vote WHERE id_approbation = ? AND id_votant = ?',
            [$id, $currentAdht['id_adht']]
        );
        if ($dejaVote > 0) {
            $this->addFlash('warning', 'Vous avez déjà voté pour cette demande.');
            return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
        }

        $roleVote    = $this->determinerRole($id, (int)($currentAdht['id_section'] ?? 0), (int)($approbation['id_section_init'] ?? 0));
        $decision    = $request->request->get('decision') === 'approuve' ? 'approuve' : 'rejete';
        $commentaire = substr($request->request->get('commentaire', ''), 0, 500);

        $this->connection->insert('comitemaore_approbation_vote', [
            'id_approbation'    => $id,
            'id_votant'         => $currentAdht['id_adht'],
            'id_section_votant' => $currentAdht['id_section'] ?? null,
            'role_vote'         => $roleVote,
            'decision'          => $decision,
            'commentaire'       => $commentaire,
            'date_vote'         => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->logService->log("vote_approbation_{$decision}", 'succes', 'approbation', $id,
            ['role' => $roleVote], $currentAdht['id_adht'], $currentAdht['login_adht'] ?? null);

        if ($decision === 'rejete') {
            $this->connection->update('comitemaore_approbation', [
                'statut'          => 'rejete',
                'date_resolution' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id_approbation' => $id]);
            $this->addFlash('danger', 'Demande rejetée.');
            return $this->redirectToRoute('app_approbation_list');
        }

        $this->verifierEtFinaliser($id, $approbation);
        $this->addFlash('success', 'Vote enregistré.');
        return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function determinerRole(int $idApprobation, int $sectionVotant, int $sectionInit): string
    {
        if ($sectionVotant === $sectionInit) return 'meme_section';
        $nb = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM comitemaore_approbation_vote WHERE id_approbation = ? AND role_vote LIKE 'autre_section%'",
            [$idApprobation]
        );
        return $nb === 0 ? 'autre_section' : 'bureau_national';
    }

    private function verifierEtFinaliser(int $idApprobation, array $approbation): void
    {
        $votes = $this->connection->fetchAllAssociative(
            "SELECT role_vote FROM comitemaore_approbation_vote WHERE id_approbation = ? AND decision = 'approuve'",
            [$idApprobation]
        );
        $roles = array_column($votes, 'role_vote');

        $complet = in_array('initiateur', $roles)
            && (in_array('meme_section', $roles) || in_array('autre_section', $roles))
            && in_array('bureau_national', $roles);

        if ($complet) {
            $this->connection->update('comitemaore_approbation', [
                'statut'          => 'approuve',
                'date_resolution' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id_approbation' => $idApprobation]);
            $this->executerOperation($approbation);
        }
    }

    private function finaliserApprobation(int $id, string $statut): void
    {
        $this->connection->update('comitemaore_approbation', [
            'statut'          => $statut,
            'date_resolution' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], ['id_approbation' => $id]);
    }

    private function executerOperation(array $approbation): void
    {
        $data = json_decode($approbation['data_operation'], true);
        switch ($approbation['type_operation']) {
            case 'ajout':
                unset($data['id_adht']);
                $data['datecreationfiche_adht'] = (new \DateTime())->format('Y-m-d');
                $this->connection->insert('comitemaore_adherent', $data);
                break;
            case 'modification':
                $idAdht = $data['id_adht'] ?? null;
                if ($idAdht) {
                    unset($data['id_adht']);
                    $data['datemodiffiche_adht'] = (new \DateTime())->format('Y-m-d');
                    $this->connection->update('comitemaore_adherent', $data, ['id_adht' => $idAdht]);
                }
                break;
            case 'suppression':
                $idAdht = $data['id_adht'] ?? null;
                if ($idAdht) $this->connection->delete('comitemaore_adherent', ['id_adht' => $idAdht]);
                break;
        }
    }
}
