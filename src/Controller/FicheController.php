<?php

namespace App\Controller;

use App\Service\AdherentFonctionService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fiches de consultation (lecture seule) pour fédérations et sections.
 * Accessible à tous les utilisateurs authentifiés (ROLE_USER).
 */
#[IsGranted('ROLE_USER')]
#[Route('/fiche')]
class FicheController extends AbstractController
{
    private const FONCTIONS = [
        'president'          => 'Président',
        'vice_president'     => 'Vice-président',
        'secretaire'         => 'Secrétaire',
        'tresorier'          => 'Trésorier',
        'secretaire_adjoint' => 'Secrétaire adjoint',
        'tresorier_adjoint'  => 'Trésorier adjoint',
        'administrateur1'    => 'Administrateur 1',
        'administrateur2'    => 'Administrateur 2',
        'administrateur3'    => 'Administrateur 3',
    ];

    public function __construct(
        private readonly Connection              $connection,
        private readonly AdherentFonctionService $fonctionService,
    ) {}

    // ------------------------------------------------------------------
    // FICHE FÉDÉRATION
    // ------------------------------------------------------------------
    #[Route('/federation/{id}', name: 'app_fiche_federation', requirements: ['id' => '\d+'])]
    public function federation(int $id): Response
    {
        $federation = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_federations WHERE id_federation = ?', [$id]
        );
        if (!$federation) throw $this->createNotFoundException("Fédération #$id introuvable.");

        // Sections rattachées à cette fédération
        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections
             WHERE id_federation = ?
             ORDER BY section',
            [$id]
        );

        // Résoudre les noms des fonctionnaires de la fédération
        $membres = $this->resoudreMembres($federation);

        // Nombre d'adhérents dans la fédération
        $nbAdherents = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comitemaore_adherent a
             JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE s.id_federation = ?',
            [$id]
        );

        return $this->render('fiche/federation.html.twig', [
            'federation'   => $federation,
            'sections'     => $sections,
            'membres'      => $membres,
            'fonctions'    => self::FONCTIONS,
            'nb_adherents' => $nbAdherents,
            'is_admin'     => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    // ------------------------------------------------------------------
    // FICHE SECTION
    // ------------------------------------------------------------------
    #[Route('/section/{id}', name: 'app_fiche_section', requirements: ['id' => '\d+'])]
    public function section(int $id): Response
    {
        $section = $this->connection->fetchAssociative(
            'SELECT s.*, f.federation AS nom_federation, f.id_federation,
                    f.nom_complet AS nom_complet_federation
             FROM comitemaore_sections s
             LEFT JOIN comitemaore_federations f ON s.id_federation = f.id_federation
             WHERE s.id_section = ?',
            [$id]
        );
        if (!$section) throw $this->createNotFoundException("Section #$id introuvable.");

        // Résoudre les noms des fonctionnaires de la section
        $membres = $this->resoudreMembres($section);

        // Adhérents de la section
        $adherents = $this->connection->fetchAllAssociative(
            'SELECT id_adht, nom_adht, prenom_adht, NIN_adh,
                    email_adht, profession_adht, cotis_adht
             FROM comitemaore_adherent
             WHERE id_section = ?
             ORDER BY nom_adht, prenom_adht',
            [$id]
        );

        $nbCotisants = count(array_filter($adherents, fn($a) => $a['cotis_adht'] === 'Oui'));

        return $this->render('fiche/section.html.twig', [
            'section'      => $section,
            'membres'      => $membres,
            'adherents'    => $adherents,
            'fonctions'    => self::FONCTIONS,
            'nb_cotisants' => $nbCotisants,
            'is_admin'     => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    // ------------------------------------------------------------------
    // Résoudre les noms des fonctionnaires depuis les colonnes id_adht
    // ------------------------------------------------------------------
    private function resoudreMembres(array $entite): array
    {
        $membres = [];
        foreach (self::FONCTIONS as $col => $label) {
            $idAdht = $entite[$col] ?? null;
            if (!$idAdht) {
                $membres[$col] = ['label' => $label, 'adherent' => null];
                continue;
            }
            $adht = $this->connection->fetchAssociative(
                'SELECT id_adht, nom_adht, prenom_adht, NIN_adh,
                        email_adht, telephonep_adht, profession_adht
                 FROM comitemaore_adherent WHERE id_adht = ?',
                [(int) $idAdht]
            );
            $membres[$col] = ['label' => $label, 'adherent' => $adht ?: null];
        }
        return $membres;
    }
}
