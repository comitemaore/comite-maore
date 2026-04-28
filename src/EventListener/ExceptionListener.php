<?php

namespace App\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class ExceptionListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Security    $security,
        #[Autowire('%kernel.environment%')]
        private readonly string      $environment,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // En mode admin : laisser Symfony afficher son écran d'erreur natif
        // SAUF en production où on force toujours la page propre
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        if ($isAdmin && $this->environment !== 'prod') {
            return; // Symfony gère nativement
        }

        // Déterminer le code HTTP
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        // Choisir le template selon le code
        $template = match (true) {
            $statusCode === 404 => 'error/404.html.twig',
            $statusCode === 403 => 'error/403.html.twig',
            $statusCode >= 500  => 'error/500.html.twig',
            default             => 'error/generic.html.twig',
        };

        try {
            $html = $this->twig->render($template, [
                'status_code'    => $statusCode,
                'status_text'    => $this->statusText($statusCode),
                'exception'      => $exception,
                'message'        => $exception->getMessage(),
                'is_admin'       => $isAdmin,
            ]);
        } catch (\Exception $e) {
            // Fallback si le template Twig plante
            $html = $this->fallbackHtml($statusCode, $exception->getMessage());
        }

        $response = new Response($html, $statusCode);
        $event->setResponse($response);
    }

    private function statusText(int $code): string
    {
        return match ($code) {
            400 => 'Requête invalide',
            403 => 'Accès refusé',
            404 => 'Page introuvable',
            405 => 'Méthode non autorisée',
            500 => 'Erreur interne du serveur',
            503 => 'Service indisponible',
            default => 'Erreur',
        };
    }

    private function fallbackHtml(int $code, string $msg): string
    {
        return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Erreur $code</title>
<style>body{font-family:Arial,sans-serif;background:#1a202c;color:#e2e8f0;
display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#2d3748;border-radius:12px;padding:40px;max-width:480px;text-align:center;}
h1{color:#fc8181;font-size:48px;margin:0 0 8px;}
p{opacity:.6;}</style></head>
<body><div class="box">
<h1>$code</h1>
<p>Une erreur est survenue. Revenez à l'accueil.</p>
<a href="/" style="color:#90cdf4;">← Retour à l'accueil</a>
</div></body></html>
HTML;
    }
}
