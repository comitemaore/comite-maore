<?php

namespace App\Controller;

use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/assistance')]
class AssistanceController extends AbstractController
{
    // À adapter dans .env ou en paramètre Symfony
    private const TECH_EMAIL  = 'admin.technique@comite-maore.org';
    private const GITHUB_REPO = 'votre-organisation/comite-maore';
    private const VPN_DIR     = 'var/vpn_configs';

    public function __construct(
        private readonly Connection      $connection,
        private readonly LogService      $logService,
        private readonly MailerInterface $mailer,
        private readonly string          $projectDir,
    ) {}

    // ------------------------------------------------------------------
    // PAGE PRINCIPALE
    // ------------------------------------------------------------------
    #[Route('', name: 'app_assistance')]
    public function index(): Response
    {
        return $this->render('assistance/index.html.twig', [
            'github_repo'  => self::GITHUB_REPO,
            'github_issues'=> 'https://github.com/' . self::GITHUB_REPO . '/issues/new/choose',
        ]);
    }

    // ------------------------------------------------------------------
    // SOUMETTRE UN TICKET DE DÉPANNAGE (mail + texte issue GitHub)
    // ------------------------------------------------------------------
    #[Route('/ticket', name: 'app_assistance_ticket', methods: ['POST'])]
    public function ticket(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('assistance_ticket', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        # $login       = $this->getUser()?->getUserIdentifier() ?? 'inconnu';
        // $user = $this->getUser();
        $login = $user ? $user->getUserIdentifier() : 'inconnu';
        $type        = $request->request->get('type', 'bug');
        $titre       = trim($request->request->get('titre', '(sans titre)'));
        $module      = trim($request->request->get('module', ''));
        $urgence     = $request->request->get('urgence', 'normal');
        $description = trim($request->request->get('description', ''));
        $reproduction= trim($request->request->get('reproduction', ''));
        $attendu     = trim($request->request->get('attendu', ''));
        $reel        = trim($request->request->get('reel', ''));
        $erreur      = trim($request->request->get('erreur', ''));

        $typeLabel = match ($type) {
            'bug'     => '🐛 Bug report',
            'support' => '❓ Question / Support',
            'feature' => '✨ Feature request',
            default   => '📋 Demande',
        };

        $urgenceLabel = match ($urgence) {
            'bloquant'  => '🔴 BLOQUANT',
            'important' => '🟠 Important',
            default     => '🟡 Normal',
        };

        // --- Corps du mail ---
        $htmlMail = $this->genererHtmlMail(
            $login, $typeLabel, $urgenceLabel, $titre,
            $module, $description, $reproduction, $attendu, $reel, $erreur
        );

        // --- Texte issue GitHub (Markdown) ---
        $issueMarkdown = $this->genererIssueMarkdown(
            $type, $login, $urgenceLabel, $titre,
            $module, $description, $reproduction, $attendu, $reel, $erreur
        );

        $mailEnvoye = false;
        $mailErreur = null;

        try {
            $email = (new Email())
                ->from('noreply@comite-maore.org')
                ->to(self::TECH_EMAIL)
                ->subject(sprintf('[Comité Maoré] %s — %s — %s', $typeLabel, $urgenceLabel, $titre))
                ->html($htmlMail)
                ->text(strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $htmlMail)));
            $this->mailer->send($email);
            $mailEnvoye = true;
        } catch (\Exception $e) {
            $mailErreur = $e->getMessage();
        }

        $this->logService->log('assistance_ticket', $mailEnvoye ? 'succes' : 'echec', 'assistance', null, [
            'type'    => $type,
            'urgence' => $urgence,
            'titre'   => $titre,
            'module'  => $module,
            'mail'    => $mailEnvoye,
        ], null, $login);

        return $this->render('assistance/ticket_envoye.html.twig', [
            'titre'          => $titre,
            'type_label'     => $typeLabel,
            'urgence_label'  => $urgenceLabel,
            'mail_envoye'    => $mailEnvoye,
            'mail_erreur'    => $mailErreur,
            'issue_markdown' => $issueMarkdown,
            'github_new'     => 'https://github.com/' . self::GITHUB_REPO . '/issues/new',
            'github_repo'    => self::GITHUB_REPO,
        ]);
    }

    // ------------------------------------------------------------------
    // UPLOAD CONFIG OpenVPN + LANCEMENT SESSION
    // ------------------------------------------------------------------
    #[Route('/vpn/upload', name: 'app_assistance_vpn_upload', methods: ['POST'])]
    public function vpnUpload(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('assistance_vpn', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('vpn_config');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier de configuration sélectionné.');
            return $this->redirectToRoute('app_assistance');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['ovpn', 'conf'], true)) {
            $this->addFlash('danger', 'Format invalide. Seuls les fichiers .ovpn et .conf sont acceptés.');
            return $this->redirectToRoute('app_assistance');
        }

        $dir = $this->projectDir . '/' . self::VPN_DIR;
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $login    = $this->getUser()?->getUserIdentifier() ?? 'admin';
        $filename = 'vpn_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $login) . '_' . date('Ymd_His') . '.' . $ext;
        $file->move($dir, $filename);
        $filepath = $dir . '/' . $filename;

        $this->logService->log('vpn_config_upload', 'succes', 'assistance', null, [
            'fichier' => $filename,
        ], null, $login);

        return $this->render('assistance/vpn_lancer.html.twig', [
            'filename' => $filename,
            'filepath' => $filepath,
            'login'    => $login,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function genererHtmlMail(
        string $login, string $type, string $urgence, string $titre,
        string $module, string $desc, string $repro, string $attendu,
        string $reel, string $erreur
    ): string {

        $esc = fn(string $s) => nl2br(htmlspecialchars($s));

        // ----------------------------
        // FIX GLOBAL (pré-calcul)
        // ----------------------------
        $moduleEsc  = $module ? $esc($module) : '—';
        $descEsc    = $desc ? $esc($desc) : '—';
        $reproEsc   = $repro ? $esc($repro) : '—';
        $attenduEsc = $attendu ? $esc($attendu) : '—';
        $reelEsc    = $reel ? $esc($reel) : '—';
        $erreurEsc  = $erreur ? $esc($erreur) : '—';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return <<<HTML
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <style>
    body{font-family:Arial,sans-serif;color:#1a202c;background:#f7fafc;margin:0;padding:20px;}
    .card{background:#fff;border-radius:8px;padding:28px;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;}
    h2{margin-top:0;color:#c53030;}
    .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:16px;}
    .section{margin-bottom:16px;padding:12px;background:#f7fafc;border-radius:6px;border-left:3px solid #4299e1;}
    .label{font-size:12px;font-weight:600;color:#718096;text-transform:uppercase;margin-bottom:4px;}
    .footer{font-size:12px;color:#a0aec0;margin-top:20px;padding-top:12px;border-top:1px solid #e2e8f0;}
    </style></head><body>

    <div class="card">
    <h2>🔧 Ticket de dépannage — Comité Maoré</h2>

    <span class="badge" style="background:#fed7d7;color:#c53030;">$urgence</span>
    <span class="badge" style="background:#e2e8f0;color:#4a5568;margin-left:6px;">$type</span>

    <div class="section"><div class="label">Titre</div><strong>{$esc($titre)}</strong></div>
    <div class="section"><div class="label">Demandeur</div>$login</div>
    <div class="section"><div class="label">Module concerné</div>$moduleEsc</div>

    <div class="section"><div class="label">Description</div>$descEsc</div>
    <div class="section"><div class="label">Étapes de reproduction</div>$reproEsc</div>
    <div class="section"><div class="label">Comportement attendu</div>$attenduEsc</div>
    <div class="section"><div class="label">Comportement réel</div>$reelEsc</div>

    <div class="section">
        <div class="label">Message d'erreur</div>
        <code style="font-size:12px;">$erreurEsc</code>
    </div>

    <div class="footer">
        Envoyé automatiquement depuis l'application Comité Maoré · $host
    </div>
    </div>

    </body></html>
    HTML;
    }

    private function genererIssueMarkdown(
        string $type, string $login, string $urgence, string $titre,
        string $module, string $desc, string $repro, string $attendu,
        string $reel, string $erreur
    ): string {
        $icon = match ($type) {
            'bug'     => '🐛',
            'support' => '❓',
            'feature' => '✨',
            default   => '📋',
        };

        $lines = ["# $icon $titre", '', "**Urgence :** $urgence", "**Demandeur :** $login", ''];

        if ($module) {
            $lines[] = "**Module concerné :** $module";
            $lines[] = '';
        }

        $lines[] = '## Description';
        $lines[] = $desc ?: '_Non renseigné_';
        $lines[] = '';

        if ($repro) {
            $lines[] = '## Étapes pour reproduire';
            foreach (explode("\n", $repro) as $i => $step) {
                if (trim($step)) $lines[] = ($i + 1) . '. ' . trim($step);
            }
            $lines[] = '';
        }

        if ($attendu) {
            $lines[] = '## Comportement attendu';
            $lines[] = $attendu;
            $lines[] = '';
        }

        if ($reel) {
            $lines[] = '## Comportement réel';
            $lines[] = $reel;
            $lines[] = '';
        }

        if ($erreur) {
            $lines[] = '## Message d\'erreur';
            $lines[] = '```';
            $lines[] = $erreur;
            $lines[] = '```';
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '_Issue générée depuis l\'application Comité Maoré — ' . date('d/m/Y H:i') . '_';

        return implode("\n", $lines);
    }
}
