<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        $username = $user?->getUserIdentifier();

        // Sélection du thème selon le rôle/utilisateur
        if ($this->isGranted('ROLE_ADMIN')) {
            $theme = 'dark';
        } else {
            $theme = 'sky'; // bleu ciel pour user1 et autres
        }

        return $this->render('dashboard/index.html.twig', [
            'username' => $username,
            'theme'    => $theme,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }
}