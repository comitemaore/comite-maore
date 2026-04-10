<?php

namespace App\Controller;

use App\Service\ApprobationMailService;
use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/approbation')]
class ApprobationTokenController extends AbstractController
{
    public function __construct(
        private readonly Connection              $connection,
        private readonly ApprobationMailService  $mailService,
        private readonly LogService              $logService,
    ) {}

    // ------------------------------------------------------------------
    // Envoyer les tokens par mail pour une demande
    // ------------------------------------------------------------------
    #[Route('/{id}/envoyer-tokens', name: 'app_approbation_envoyer_tokens', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function envoyerTokens(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('envoyer_tokens_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $approbation = $this->connection->fetchAssociative(
            "SELECT * FROM comitemaore_approbation WHERE id_approbation = ? AND statut = 'en_attente'",
            [$id]
        );
        if (!$approbation) {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');
            return $this->redirectToRoute('app_approbation_list');
        }

        try {
            $resultats = $this->mailService->envoyerTokens($id);
            $nb        = count(array_filter($resultats, fn($r) => $r['statut'] === 'envoye'));
            $nbEchec   = count($resultats) - $nb;

            $this->logService->log('envoi_tokens', 'succes', 'approbation', $id,
                ['resultats' => $resultats], null, $this->getUser()?->getUserIdentifier());

            $this->addFlash('success', "$nb mail(s) envoyé(s) avec succès."
                . ($nbEchec > 0 ? " $nbEchec échec(s) — vérifiez les emails des administrateurs." : ''));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Vote via lien cliquable (token dans l'URL)
    // Accessible sans authentification
    // ------------------------------------------------------------------
    #[Route('/token/{token}/{decision}', name: 'app_approbation_token_vote', methods: ['GET', 'POST'])]
    public function voteParToken(string $token, string $decision, Request $request): Response
    {
        $validation = $this->mailService->validerToken($token, $decision);

        if (!$validation['valide']) {
            return $this->render('approbation/token_resultat.html.twig', [
                'succes'  => false,
                'message' => $validation['message'],
                'token'   => null,
            ]);
        }

        $tokenRow    = $validation['row'];
        $approbation = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation WHERE id_approbation = ?',
            [$tokenRow['id_approbation']]
        );

        if ($approbation['statut'] !== 'en_attente') {
            return $this->render('approbation/token_resultat.html.twig', [
                'succes'  => false,
                'message' => 'Cette demande a déjà été traitée.',
                'token'   => null,
            ]);
        }

        // Confirmation POST requise avant d'enregistrer
        if ($request->isMethod('GET')) {
            $adherent = $this->connection->fetchAssociative(
                'SELECT prenom_adht, nom_adht FROM comitemaore_adherent WHERE id_adht = ?',
                [$tokenRow['id_adht']]
            );
            return $this->render('approbation/token_confirmer.html.twig', [
                'token'       => $token,
                'decision'    => $decision,
                'tokenRow'    => $tokenRow,
                'approbation' => $approbation,
                'adherent'    => $adherent,
            ]);
        }

        // Enregistrement du vote
        $ip = $request->getClientIp();
        $this->mailService->consommerToken($tokenRow, $decision, $ip);
        $this->enregistrerVote($tokenRow, $decision, $approbation);

        $this->logService->log("token_vote_{$decision}", 'succes', 'approbation',
            $tokenRow['id_approbation'],
            ['role' => $tokenRow['role_destinataire'], 'ip' => $ip],
            $tokenRow['id_adht'], null);

        return $this->render('approbation/token_resultat.html.twig', [
            'succes'      => true,
            'decision'    => $decision,
            'message'     => $decision === 'approuve'
                ? 'Votre approbation a bien été enregistrée.'
                : 'Votre rejet a bien été enregistré.',
            'token'       => $tokenRow,
            'approbation' => $approbation,
        ]);
    }

    // ------------------------------------------------------------------
    // Vote via code de secours (saisi dans l'appli)
    // ------------------------------------------------------------------
    #[Route('/{id}/code', name: 'app_approbation_code_vote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function voteParCode(int $id, Request $request): Response
    {
        $code     = strtolower(trim($request->request->get('code', '')));
        $decision = $request->request->get('decision') === 'approuve' ? 'approuve' : 'rejete';

        // Trouver l'id_adht de l'utilisateur connecté dans comitemaore_adherent
        $login = $this->getUser()?->getUserIdentifier();
        $adht  = $this->connection->fetchAssociative(
            'SELECT id_adht FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        );

        if (!$adht) {
            $this->addFlash('danger', 'Votre compte n\'est pas associé à un adhérent.');
            return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
        }

        $validation = $this->mailService->validerCode($id, $code, $adht['id_adht']);

        if (!$validation['valide']) {
            $this->addFlash('danger', $validation['message']);
            return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
        }

        $tokenRow    = $validation['row'];
        $approbation = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation WHERE id_approbation = ?', [$id]
        );

        if ($approbation['statut'] !== 'en_attente') {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
        }

        $ip = $request->getClientIp();
        $this->mailService->consommerToken($tokenRow, $decision, $ip);
        $this->enregistrerVote($tokenRow, $decision, $approbation);

        $this->logService->log("code_vote_{$decision}", 'succes', 'approbation', $id,
            ['role' => $tokenRow['role_destinataire'], 'code' => $code],
            $adht['id_adht'], $login);

        $this->addFlash('success', $decision === 'approuve'
            ? 'Approbation enregistrée via code de secours.'
            : 'Rejet enregistré via code de secours.');

        return $this->redirectToRoute('app_approbation_show', ['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Configuration des tokens (durée, expéditeur)
    // ------------------------------------------------------------------
    #[Route('/config', name: 'app_approbation_config', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function config(Request $request): Response
    {
        $config = $this->mailService->getConfig();

        if ($request->isMethod('POST')) {
            $heures  = $request->request->getInt('duree_heures', 8);
            $minutes = $request->request->getInt('duree_minutes', 0);
            $email   = trim($request->request->get('email_expediteur', ''));
            $nom     = trim($request->request->get('nom_expediteur', ''));
            $login   = $this->getUser()?->getUserIdentifier();

            $this->mailService->sauvegarderConfig($heures, $minutes, $email, $nom, $login);
            $this->logService->log('modif_config_approbation', 'succes', 'approbation', null,
                ['heures' => $heures, 'minutes' => $minutes], null, $login);

            $this->addFlash('success', 'Configuration mise à jour.');
            return $this->redirectToRoute('app_approbation_config');
        }

        // Aperçu de l'expiration
        $expiration = $this->mailService->calculerExpiration();

        return $this->render('approbation/config.html.twig', [
            'config'     => $config,
            'expiration' => $expiration,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function enregistrerVote(array $tokenRow, string $decision, array $approbation): void
    {
        // Vérifier si ce votant n'a pas déjà voté
        $existe = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comitemaore_approbation_vote
             WHERE id_approbation = ? AND id_votant = ?',
            [$tokenRow['id_approbation'], $tokenRow['id_adht']]
        );

        if (!$existe) {
            $this->connection->insert('comitemaore_approbation_vote', [
                'id_approbation'    => $tokenRow['id_approbation'],
                'id_votant'         => $tokenRow['id_adht'],
                'id_section_votant' => null,
                'role_vote'         => $tokenRow['role_destinataire'],
                'decision'          => $decision,
                'commentaire'       => 'Vote par ' . ($tokenRow['role_destinataire'] === 'initiateur' ? 'lien mail' : 'token mail'),
                'date_vote'         => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        if ($decision === 'rejete') {
            $this->connection->update('comitemaore_approbation', [
                'statut'          => 'rejete',
                'date_resolution' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id_approbation' => $tokenRow['id_approbation']]);
            return;
        }

        // Vérifier si les 3 rôles requis ont approuvé
        $votes = $this->connection->fetchAllAssociative(
            "SELECT role_vote FROM comitemaore_approbation_vote
             WHERE id_approbation = ? AND decision = 'approuve'",
            [$tokenRow['id_approbation']]
        );
        $roles = array_column($votes, 'role_vote');

        $complet = in_array('initiateur', $roles)
            && (in_array('meme_section', $roles) || in_array('autre_section', $roles))
            && in_array('bureau_national', $roles);

        if ($complet) {
            $this->connection->update('comitemaore_approbation', [
                'statut'          => 'approuve',
                'date_resolution' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id_approbation' => $tokenRow['id_approbation']]);

            $this->executerOperation($approbation);
        }
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
                if ($idAdht) {
                    $this->connection->delete('comitemaore_adherent', ['id_adht' => $idAdht]);
                }
                break;
        }
    }
}
