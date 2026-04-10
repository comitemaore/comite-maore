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
#[Route('/logs')]
class LogController extends AbstractController
{
    public function __construct(
        private readonly Connection  $connection,
        private readonly LogService  $logService,
    ) {}

    #[Route('', name: 'app_log_list')]
    public function list(Request $request): Response
    {
        $module  = $request->query->get('module');
        $action  = $request->query->get('action');
        $logs    = $this->logService->getLogs(300, $module ?: null, $action ?: null);

        $modules = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT module FROM comitemaore_log WHERE module IS NOT NULL ORDER BY module'
        );

        return $this->render('log/list.html.twig', [
            'logs'      => $logs,
            'modules'   => $modules,
            'filModule' => $module,
            'filAction' => $action,
            'is_admin'  => true,
            'current'   => null,
        ]);
    }

    #[Route('/jour', name: 'app_log_today')]
    public function today(): Response
    {
        $login = $this->getUser()?->getUserIdentifier();
        $adht  = $login ? ($this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_adherent WHERE login_adht = ?', [$login]
        ) ?: null) : null;

        $logs = $this->logService->getLogsJour($adht['id_adht'] ?? null);

        return $this->render('log/today.html.twig', [
            'logs'     => $logs,
            'is_admin' => true,
            'current'  => $adht,
        ]);
    }
}
