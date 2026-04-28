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
#[Route('/finance')]
class FinanceController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LogService $logService,
    ) {}

    private function currentAdherent(): ?array
    {
        $login = $this->getUser()?->getUserIdentifier();
        return $login ? ($this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null) : null;
    }

    // ------------------------------------------------------------------
    // Fiche financière d'un adhérent
    // ------------------------------------------------------------------
    #[Route('/{idAdht}', name: 'app_finance_show', methods: ['GET'], requirements: ['idAdht' => '\d+'])]
    public function show(int $idAdht): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$idAdht]
        );
        if (!$adherent) throw $this->createNotFoundException();

        // Historique complet
        $historique = $this->connection->fetchAllAssociative(
            'SELECT * FROM finance_adh WHERE id_adht = ? ORDER BY date_operation DESC', [$idAdht]
        );
        // Cotisations dues non soldées
        $cotisationsDues = $this->connection->fetchAllAssociative(
            "SELECT * FROM comitemaore_cotisation_due
             WHERE id_adht = ? AND statut != 'soldee'
             ORDER BY annee DESC", [$idAdht]
        );

        $current = $this->currentAdherent();
        $this->logService->log('view_finance', 'info', 'finance', $idAdht,
            [], $current['id_adht'] ?? null, $current['login_adht'] ?? null);

        return $this->render('finance/show.html.twig', [
            'adherent'        => $adherent,
            'historique'      => $historique,
            'cotisations_dues'=> $cotisationsDues,
            'is_admin'        => $this->isGranted('ROLE_ADMIN'),
            'current'         => $current,
        ]);
    }

    // ------------------------------------------------------------------
    // Formulaire opération financière
    // ------------------------------------------------------------------
    #[Route('/{idAdht}/operation', name: 'app_finance_operation', methods: ['GET', 'POST'], requirements: ['idAdht' => '\d+'])]
    public function operation(int $idAdht, Request $request): Response
    {
        $adherent = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE id_adht = ?', [$idAdht]
        );
        if (!$adherent) throw $this->createNotFoundException();

        $current = $this->currentAdherent();
        $error   = null;

        // Cotisations dues disponibles
        $cotisationsDues = $this->connection->fetchAllAssociative(
            "SELECT * FROM comitemaore_cotisation_due
             WHERE id_adht = ? AND statut != 'soldee'
             ORDER BY annee", [$idAdht]
        );

        if ($request->isMethod('POST')) {
            $typeOp = $request->request->get('type_operation');

            try {
                $this->connection->beginTransaction();
                $finData = $this->buildFinanceData($request, $idAdht, $adherent, $current);

                // Insérer dans finance_adh
                $this->connection->insert('finance_adh', $finData);
                $idFinAdh = (int) $this->connection->lastInsertId();

                // Répartition cotisation dans tab_finance
                if (in_array($typeOp, ['cotisation', 'cotisation_due']) && $finData['montant'] > 0) {
                    $this->repartirCotisation($finData['montant'], $adherent);
                }

                // Marquer cotisation due comme soldée si applicable
                if ($typeOp === 'cotisation_due') {
                    $idDue = $request->request->getInt('id_cotis_due');
                    $idDue = (is_numeric($id)) ? (int) $idDue : null;

                    if ($idDue) {
                        $this->connection->update('comitemaore_cotisation_due', [
                            'montant_paye'  => $this->connection->fetchOne(
                                'SELECT montant_du FROM comitemaore_cotisation_due WHERE id_cotis_due = ?', [$idDue]
                            ),
                            'statut' => 'soldee',
                        ], ['id_cotis_due' => $idDue]);
                    }
                }

                // Renouvellement carte
                if ($typeOp === 'renouvellement_carte') {
                    $this->connection->update('comitemaore_adherent', [
                        'date_echeance_cotis' => $finData['date_renouvellement'],
                        'cotis_adht'          => 'Oui',
                    ], ['id_adht' => $idAdht]);
                }

                $this->connection->commit();
                $this->logService->log("finance_{$typeOp}", 'succes', 'finance', $idFinAdh,
                    ['montant' => $finData['montant']], $current['id_adht'] ?? null, $current['login_adht'] ?? null);

                $this->addFlash('success', 'Opération enregistrée avec succès.');
                return $this->redirectToRoute('app_finance_show', ['idAdht' => $idAdht]);

            } catch (\Exception $e) {
                $this->connection->rollBack();
                $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
            }
        }

        return $this->render('finance/operation.html.twig', [
            'adherent'         => $adherent,
            'cotisations_dues' => $cotisationsDues,
            'is_admin'         => $this->isGranted('ROLE_ADMIN'),
            'current'          => $current,
            'error'            => $error,
            'annee_courante'   => (int) date('Y'),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function buildFinanceData(Request $req, int $idAdht, array $adherent, ?array $current): array
    {
        $typeOp  = $req->request->get('type_operation');
        $montant = (float) str_replace(',', '.', $req->request->get('montant', '0'));

        $data = [
            'id_adht'             => $idAdht,
            'id_section'          => $adherent['id_section'],
            'type_operation'      => $typeOp,
            'montant'             => $montant,
            'montant_burnatexec'  => 0,
            'montant_fedcorresp'  => 0,
            'montant_sectcorresp' => 0,
            'date_operation'      => $req->request->get('date_operation') ?: date('Y-m-d'),
            'date_renouvellement' => $req->request->get('date_renouvellement') ?: null,
            'annee_cotisation'    => $req->request->get('annee_cotisation') ?: null,
            'nature_don'          => $req->request->get('nature_don', ''),
            'note'                => $req->request->get('note', ''),
            'cotis_due_payee'     => 0,
            'id_fin_due_regle'    => $req->request->getInt('id_cotis_due') ?: null,
            'enregistre_par'      => $current['id_adht'] ?? null,
            'date_enregistrement' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        if (in_array($typeOp, ['cotisation', 'cotisation_due']) && $montant > 0) {
            $data['montant_burnatexec']  = round($montant * 0.50, 2);
            $data['montant_fedcorresp']  = round($montant * 0.30, 2);
            $data['montant_sectcorresp'] = round($montant * 0.20, 2);
        }

        if ($typeOp === 'cotisation_due') {
            $data['cotis_due_payee'] = 1;
        }

        return $data;
    }

    private function repartirCotisation(float $montant, array $adherent): void
    {
        $annee     = (int) date('Y');
        $idSection = $adherent['id_section'];

        // Upsert dans tab_finance
        $existing = $this->connection->fetchAssociative(
            'SELECT * FROM tab_finance WHERE id_section = ? AND annee_finance = ?',
            [$idSection, $annee]
        );

        $bne  = round($montant * 0.50, 2);
        $fed  = round($montant * 0.30, 2);
        $sect = round($montant * 0.20, 2);

        if ($existing) {
            $this->connection->executeStatement(
                'UPDATE tab_finance SET
                    fin_burnatexec  = fin_burnatexec  + ?,
                    fin_fedcorresp  = fin_fedcorresp  + ?,
                    fin_sectcorresp = fin_sectcorresp + ?,
                    fin_total_cotis = fin_total_cotis + ?
                 WHERE id_section = ? AND annee_finance = ?',
                [$bne, $fed, $sect, $montant, $idSection, $annee]
            );
        } else {
            $this->connection->insert('tab_finance', [
                'id_section'      => $idSection,
                'annee_finance'   => $annee,
                'fin_burnatexec'  => $bne,
                'fin_fedcorresp'  => $fed,
                'fin_sectcorresp' => $sect,
                'fin_total_cotis' => $montant,
            ]);
        }
    }
}
