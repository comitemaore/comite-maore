<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApprobationMailService
{
    public function __construct(
        private readonly Connection             $connection,
        private readonly MailerInterface        $mailer,
        private readonly UrlGeneratorInterface  $urlGenerator,
    ) {}

    // ------------------------------------------------------------------
    // Calcule la date d'expiration selon la config
    // max 12h ET avant minuit du jour courant
    // ------------------------------------------------------------------
    public function calculerExpiration(): \DateTime
    {
        $config = $this->getConfig();
        $heures  = min((int) $config['duree_heures'], 12);
        $minutes = min((int) $config['duree_minutes'], 59);

        $now        = new \DateTime();
        $expiration = (clone $now)->modify("+{$heures} hours +{$minutes} minutes");

        // Plafonner à 23h59:59 du jour courant
        $minuit = (clone $now)->setTime(23, 59, 59);
        if ($expiration > $minuit) {
            $expiration = $minuit;
        }

        return $expiration;
    }

    // ------------------------------------------------------------------
    // Envoie les 3 tokens pour une demande d'approbation
    // Rôles : initiateur (même section), autre_section, bureau_national
    // ------------------------------------------------------------------
    public function envoyerTokens(int $idApprobation): array
    {
        $approbation = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation WHERE id_approbation = ?',
            [$idApprobation]
        );
        if (!$approbation) {
            throw new \RuntimeException("Approbation #$idApprobation introuvable.");
        }

        $idSection   = (int) ($approbation['id_section_init'] ?? 0);
        $destinataires = $this->resoudreDestinataires($idSection, $approbation);
        $config        = $this->getConfig();
        $expiration    = $this->calculerExpiration();
        $resultats     = [];

        foreach ($destinataires as $dest) {
            if (empty($dest['email'])) {
                $resultats[] = ['role' => $dest['role'], 'statut' => 'echec', 'raison' => 'Email manquant'];
                continue;
            }

            $token = $this->genererToken();

            // Supprimer un éventuel token précédent pour ce même admin/approbation
            $this->connection->executeStatement(
                'DELETE FROM comitemaore_approbation_token
                 WHERE id_approbation = ? AND id_adht = ?',
                [$idApprobation, $dest['id_adht']]
            );

            // Enregistrer le token
            $this->connection->insert('comitemaore_approbation_token', [
                'id_approbation'    => $idApprobation,
                'token'             => $token,
                'role_destinataire' => $dest['role'],
                'id_adht'           => $dest['id_adht'],
                'email_envoye_a'    => $dest['email'],
                'expire_a'          => $expiration->format('Y-m-d H:i:s'),
                'utilise'           => 0,
                'date_envoi'        => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Générer les URLs
            $urlApprouver = $this->urlGenerator->generate(
                'app_approbation_token_vote',
                ['token' => $token, 'decision' => 'approuve'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $urlRejeter = $this->urlGenerator->generate(
                'app_approbation_token_vote',
                ['token' => $token, 'decision' => 'rejete'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Envoyer le mail
            try {
                $this->envoyerMail(
                    $dest,
                    $approbation,
                    $token,
                    $urlApprouver,
                    $urlRejeter,
                    $expiration,
                    $config
                );
                $resultats[] = ['role' => $dest['role'], 'statut' => 'envoye', 'email' => $dest['email']];
            } catch (\Exception $e) {
                $resultats[] = ['role' => $dest['role'], 'statut' => 'echec', 'raison' => $e->getMessage()];
            }
        }

        return $resultats;
    }

    // ------------------------------------------------------------------
    // Valider un token (lien cliquable)
    // ------------------------------------------------------------------
    public function validerToken(string $token, string $decision): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation_token WHERE token = ?',
            [$token]
        );

        if (!$row) {
            return ['valide' => false, 'message' => 'Token introuvable.'];
        }
        if ($row['utilise']) {
            return ['valide' => false, 'message' => 'Ce token a déjà été utilisé.'];
        }
        if (new \DateTime() > new \DateTime($row['expire_a'])) {
            return ['valide' => false, 'message' => 'Ce token a expiré.'];
        }
        if (!in_array($decision, ['approuve', 'rejete'])) {
            return ['valide' => false, 'message' => 'Décision invalide.'];
        }

        return ['valide' => true, 'row' => $row, 'decision' => $decision];
    }

    // ------------------------------------------------------------------
    // Valider un code à 6 chiffres (code de secours)
    // ------------------------------------------------------------------
    public function validerCode(int $idApprobation, string $code, int $idAdht): array
    {
        // Le code est les 6 premiers caractères hex du token
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation_token
             WHERE id_approbation = ? AND id_adht = ? AND utilise = 0
               AND expire_a > NOW()
               AND LEFT(token, 6) = ?',
            [$idApprobation, $idAdht, strtolower($code)]
        );

        if (!$row) {
            return ['valide' => false, 'message' => 'Code invalide, expiré ou déjà utilisé.'];
        }

        return ['valide' => true, 'row' => $row];
    }

    // ------------------------------------------------------------------
    // Marquer un token comme utilisé
    // ------------------------------------------------------------------
    public function consommerToken(array $tokenRow, string $decision, string $ip): void
    {
        $this->connection->update('comitemaore_approbation_token', [
            'utilise'           => 1,
            'decision'          => $decision,
            'date_utilisation'  => (new \DateTime())->format('Y-m-d H:i:s'),
            'ip_utilisation'    => $ip,
        ], ['id_token' => $tokenRow['id_token']]);
    }

    // ------------------------------------------------------------------
    // Getters utilitaires
    // ------------------------------------------------------------------
    public function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_approbation_config WHERE id_config = 1'
        ) ?: [
            'duree_heures'     => 8,
            'duree_minutes'    => 0,
            'email_expediteur' => 'noreply@comite-maore.org',
            'nom_expediteur'   => 'Comité Maoré',
        ];
    }

    public function sauvegarderConfig(int $heures, int $minutes, string $email, string $nom, string $login): void
    {
        $heures  = min(max(1, $heures), 12);
        $minutes = min(max(0, $minutes), 59);

        $this->connection->executeStatement(
            'INSERT INTO comitemaore_approbation_config
                (id_config, duree_heures, duree_minutes, email_expediteur, nom_expediteur, modifie_par)
             VALUES (1, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                duree_heures = VALUES(duree_heures),
                duree_minutes = VALUES(duree_minutes),
                email_expediteur = VALUES(email_expediteur),
                nom_expediteur = VALUES(nom_expediteur),
                modifie_par = VALUES(modifie_par)',
            [$heures, $minutes, $email, $nom, $login]
        );
    }

    public function tokensDeApprobation(int $idApprobation): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT t.*, a.prenom_adht, a.nom_adht
             FROM comitemaore_approbation_token t
             LEFT JOIN comitemaore_adherent a ON t.id_adht = a.id_adht
             WHERE t.id_approbation = ?
             ORDER BY t.date_envoi",
            [$idApprobation]
        );
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function genererToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caractères hex
    }

    private function resoudreDestinataires(int $idSection, array $approbation): array
    {
        $destinataires = [];

        // 1. Initiateur = administrateur1 de la section concernée
        $admin1 = $this->fetchAdminEmail($idSection, 'administrateur1');
        if ($admin1) {
            $destinataires[] = array_merge($admin1, ['role' => 'initiateur']);
        }

        // 2. Admin d'une autre section = administrateur1 d'une section différente
        $autreSection = $this->connection->fetchAssociative(
            "SELECT s.administrateur1 AS id_adht_col, a.id_adht, a.email_adht AS email,
                    a.prenom_adht, a.nom_adht
             FROM comitemaore_sections s
             JOIN comitemaore_adherent a ON s.administrateur1 = a.id_adht
             WHERE s.id_section != ? AND s.administrateur1 IS NOT NULL
               AND a.email_adht IS NOT NULL AND a.email_adht != ''
             LIMIT 1",
            [$idSection]
        );
        if ($autreSection) {
            $destinataires[] = [
                'id_adht' => $autreSection['id_adht'],
                'email'   => $autreSection['email'],
                'prenom'  => $autreSection['prenom_adht'],
                'nom'     => $autreSection['nom_adht'],
                'role'    => 'autre_section',
            ];
        }

        // 3. Admin du bureau national = administrateur1 du bureau exécutif national
        $bureau = $this->connection->fetchAssociative(
            "SELECT b.administrateur1, a.id_adht, a.email_adht AS email,
                    a.prenom_adht, a.nom_adht
             FROM comitemaore_burexecnat b
             JOIN comitemaore_adherent a ON b.administrateur1 = a.id_adht
             WHERE b.actif = 1 AND a.email_adht IS NOT NULL AND a.email_adht != ''
             ORDER BY b.annee_mandat DESC LIMIT 1"
        );
        if ($bureau) {
            $destinataires[] = [
                'id_adht' => $bureau['id_adht'],
                'email'   => $bureau['email'],
                'prenom'  => $bureau['prenom_adht'],
                'nom'     => $bureau['nom_adht'],
                'role'    => 'bureau_national',
            ];
        }

        return $destinataires;
    }

    private function fetchAdminEmail(int $idSection, string $colonne): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT a.id_adht, a.email_adht AS email, a.prenom_adht AS prenom, a.nom_adht AS nom
             FROM comitemaore_sections s
             JOIN comitemaore_adherent a ON s.$colonne = a.id_adht
             WHERE s.id_section = ?
               AND a.email_adht IS NOT NULL AND a.email_adht != ''",
            [$idSection]
        );
        return $row ?: null;
    }

    private function envoyerMail(
        array     $dest,
        array     $approbation,
        string    $token,
        string    $urlApprouver,
        string    $urlRejeter,
        \DateTime $expiration,
        array     $config
    ): void {
        $codeSecours = strtoupper(substr($token, 0, 6));
        $typeOp      = $approbation['type_operation'];
        $roleLabel   = match ($dest['role']) {
            'initiateur'     => 'initiateur (même section)',
            'meme_section'   => 'administrateur de la même section',
            'autre_section'  => 'administrateur d\'une autre section',
            'bureau_national'=> 'administrateur du Bureau National',
            default          => $dest['role'],
        };

        $sujet = sprintf(
            '[Comité Maoré] Approbation requise — %s d\'adhérent #%d',
            ucfirst($typeOp),
            $approbation['id_adht_cible']
        );

        $expireStr = $expiration->format('d/m/Y à H\hi');

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;color:#1a202c;background:#f7fafc;margin:0;padding:20px;}
  .card{background:#fff;border-radius:8px;padding:32px;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;}
  h2{color:#2b6cb0;margin-top:0;}
  .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;}
  .badge-ajout{background:#c6f6d5;color:#276749;}
  .badge-modif{background:#bee3f8;color:#2c5282;}
  .badge-supp{background:#fed7d7;color:#742a2a;}
  .btn{display:inline-block;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:15px;margin:6px 4px;}
  .btn-green{background:#38a169;color:#fff;}
  .btn-red{background:#e53e3e;color:#fff;}
  .code{font-size:28px;font-weight:700;letter-spacing:6px;color:#2b6cb0;padding:12px 24px;background:#ebf8ff;border-radius:8px;display:inline-block;margin:8px 0;}
  .footer{font-size:12px;color:#718096;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;}
  .info-row{display:flex;gap:12px;margin:6px 0;font-size:14px;}
  .info-label{color:#718096;min-width:120px;}
</style></head>
<body>
<div class="card">
  <h2>🏛 Comité Maoré — Approbation requise</h2>

  <p>Bonjour <strong>{$dest['prenom']} {$dest['nom']}</strong>,</p>

  <p>Une demande d'approbation nécessite votre validation en tant que <strong>{$roleLabel}</strong>.</p>

  <div style="background:#f7fafc;border-radius:6px;padding:16px;margin:16px 0;">
    <div class="info-row">
      <span class="info-label">Opération</span>
      <span><span class="badge badge-{$typeOp}">{$typeOp}</span></span>
    </div>
    <div class="info-row">
      <span class="info-label">Demande #</span>
      <span>{$approbation['id_approbation']}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Expire le</span>
      <span><strong>{$expireStr}</strong></span>
    </div>
  </div>

  <p style="font-weight:600;margin-bottom:8px;">Cliquez pour voter :</p>

  <div style="text-align:center;margin:20px 0;">
    <a href="{$urlApprouver}" class="btn btn-green">✓ Approuver</a>
    <a href="{$urlRejeter}"   class="btn btn-red">✗ Rejeter</a>
  </div>

  <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">

  <p style="font-size:13px;color:#718096;">Si les liens ne fonctionnent pas, utilisez ce <strong>code de secours</strong> dans l'application :</p>

  <div style="text-align:center;">
    <div class="code">{$codeSecours}</div>
  </div>

  <p style="font-size:12px;color:#a0aec0;text-align:center;">
    Saisissez ce code sur la page de la demande d'approbation #{$approbation['id_approbation']}
  </p>

  <div class="footer">
    Ce mail a été envoyé automatiquement par le système Comité Maoré.<br>
    Si vous n'êtes pas concerné par cette demande, ignorez ce message.<br>
    Token valide jusqu'au {$expireStr}.
  </div>
</div>
</body>
</html>
HTML;

        $email = (new Email())
            ->from(sprintf('%s <%s>', $config['nom_expediteur'], $config['email_expediteur']))
            ->to($dest['email'])
            ->subject($sujet)
            ->html($htmlBody)
            ->text(strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $htmlBody)));

        $this->mailer->send($email);
    }
}
