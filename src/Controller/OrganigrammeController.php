<?php

namespace App\Controller;

use App\Service\LogService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/organigramme')]
class OrganigrammeController extends AbstractController
{
    // Fonctions communes aux 3 niveaux
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
        private readonly Connection $connection,
        private readonly LogService $logService,
    ) {}

    // ------------------------------------------------------------------
    // PAGE PRINCIPALE — vue d'ensemble des 3 niveaux
    // ------------------------------------------------------------------
    #[Route('', name: 'app_organigramme')]
    public function index(): Response
    {
        $sections    = $this->sectionsAvecNoms();
        $federations = $this->federationsAvecNoms();
        $bureau      = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_burexecnat WHERE actif = 1 ORDER BY annee_mandat DESC LIMIT 1'
        ) ?: [];
        $bureauNoms  = $bureau ? $this->resolveNoms($bureau) : [];

        return $this->render('organigramme/index.html.twig', [
            'sections'    => $sections,
            'federations' => $federations,
            'bureau'      => $bureau,
            'bureauNoms'  => $bureauNoms,
            'fonctions'   => self::FONCTIONS,
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER UNE SECTION
    // ------------------------------------------------------------------
    #[Route('/section/{id}', name: 'app_organigramme_section', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function section(int $id, Request $request): Response
    {
        $section = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_sections WHERE id_section = ?', [$id]
        );
        if (!$section) throw $this->createNotFoundException();

        $adherents = $this->adherentsDeLaSection($id);
        $error     = null;

        if ($request->isMethod('POST')) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Seul admin peut modifier.');
            }
            $data = $this->extractFonctions($request);
            $data['modifie_par'] = $this->getUser()?->getUserIdentifier();

            $this->connection->update('comitemaore_sections', $data, ['id_section' => $id]);
            $this->log('modif_section', 'succes', $id, ['section' => $section['section']]);
            $this->addFlash('success', "Section « {$section['section']} » mise à jour.");
            return $this->redirectToRoute('app_organigramme');
        }

        return $this->render('organigramme/form.html.twig', [
            'entite'    => $section,
            'adherents' => $adherents,
            'fonctions' => self::FONCTIONS,
            'mode'      => 'section',
            'titre'     => 'Section : ' . $section['section'],
            'back_url'  => $this->generateUrl('app_organigramme'),
            'error'     => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER UNE FÉDÉRATION
    // ------------------------------------------------------------------
    #[Route('/federation/{id}', name: 'app_organigramme_federation', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function federation(int $id, Request $request): Response
    {
        $federation = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_federations WHERE id_federation = ?', [$id]
        );
        if (!$federation) throw $this->createNotFoundException();

        // Adhérents de toutes les sections rattachées à cette fédération
        $adherents = $this->adherentsDeLaFederation($id);
        $error     = null;

        if ($request->isMethod('POST')) {
            $data = $this->extractFonctions($request);
            $data['nom_complet']  = trim($request->request->get('nom_complet', ''));
            $data['modifie_par']  = $this->getUser()?->getUserIdentifier();

            $this->connection->update('comitemaore_federations', $data, ['id_federation' => $id]);
            $this->log('modif_federation', 'succes', $id, ['federation' => $federation['federation']]);
            $this->addFlash('success', "Fédération « {$federation['federation']} » mise à jour.");
            return $this->redirectToRoute('app_organigramme');
        }

        return $this->render('organigramme/form.html.twig', [
            'entite'    => $federation,
            'adherents' => $adherents,
            'fonctions' => self::FONCTIONS,
            'mode'      => 'federation',
            'titre'     => 'Fédération : ' . ($federation['nom_complet'] ?? $federation['federation']),
            'back_url'  => $this->generateUrl('app_organigramme'),
            'error'     => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER LE BUREAU EXÉCUTIF NATIONAL
    // ------------------------------------------------------------------
    #[Route('/bureau', name: 'app_organigramme_bureau', methods: ['GET', 'POST'])]
    public function bureau(Request $request): Response
    {
        $bureau = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_burexecnat WHERE actif = 1 ORDER BY annee_mandat DESC LIMIT 1'
        );

        // Créer si inexistant
        if (!$bureau) {
            $this->connection->insert('comitemaore_burexecnat', [
                'nom_bureau'   => 'Bureau Exécutif National',
                'annee_mandat' => (int) date('Y'),
                'actif'        => 1,
            ]);
            $bureau = $this->connection->fetchAssociative(
                'SELECT * FROM comitemaore_burexecnat ORDER BY id_burexec DESC LIMIT 1'
            );
        }

        // Tous les adhérents pour le bureau national
        $adherents = $this->connection->fetchAllAssociative(
            'SELECT id_adht, nom_adht, prenom_adht, profession_adht, NIN_adh, id_section
             FROM comitemaore_adherent
             ORDER BY nom_adht, prenom_adht'
        );

        $error = null;

        if ($request->isMethod('POST')) {
            $data = $this->extractFonctions($request);
            $data['nom_bureau']  = trim($request->request->get('nom_bureau', 'Bureau Exécutif National'));
            $data['modifie_par'] = $this->getUser()?->getUserIdentifier();

            $this->connection->update('comitemaore_burexecnat', $data, ['id_burexec' => $bureau['id_burexec']]);
            $this->log('modif_burexecnat', 'succes', $bureau['id_burexec'], []);
            $this->addFlash('success', 'Bureau Exécutif National mis à jour.');
            return $this->redirectToRoute('app_organigramme');
        }

        return $this->render('organigramme/form.html.twig', [
            'entite'    => $bureau,
            'adherents' => $adherents,
            'fonctions' => self::FONCTIONS,
            'mode'      => 'bureau',
            'titre'     => 'Bureau Exécutif National — ' . ($bureau['annee_mandat'] ?? date('Y')),
            'back_url'  => $this->generateUrl('app_organigramme'),
            'error'     => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // AJAX : recherche d'adhérent par nom (pour l'autocomplete)
    // ------------------------------------------------------------------
    #[Route('/adherent/search', name: 'app_organigramme_adherent_search', methods: ['GET'])]
    public function searchAdherent(Request $request): JsonResponse
    {
        $q         = $request->query->get('q', '');
        $idSection = $request->query->getInt('section', 0);

        $qb = $this->connection->createQueryBuilder()
            ->select('id_adht, nom_adht, prenom_adht, profession_adht, NIN_adh')
            ->from('comitemaore_adherent')
            ->where('nom_adht LIKE :q OR prenom_adht LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->setMaxResults(15);

        if ($idSection > 0) {
            $qb->andWhere('id_section = :sec')->setParameter('sec', $idSection);
        }

        $results = $qb->fetchAllAssociative();
        return $this->json($results);
    }

    // ------------------------------------------------------------------
    // Helpers privés
    // ------------------------------------------------------------------
    private function extractFonctions(Request $request): array
    {
        $data = [];
        foreach (array_keys(self::FONCTIONS) as $col) {
            $val = $request->request->getInt($col);
            $data[$col] = $val > 0 ? $val : null;
        }
        return $data;
    }

    private function resolveNoms(array $row): array
    {
        $noms = [];
        foreach (array_keys(self::FONCTIONS) as $col) {
            if (!empty($row[$col])) {
                $adht = $this->connection->fetchAssociative(
                    'SELECT prenom_adht, nom_adht FROM comitemaore_adherent WHERE id_adht = ?',
                    [$row[$col]]
                );
                $noms[$col] = $adht
                    ? $adht['prenom_adht'] . ' ' . $adht['nom_adht']
                    : '#' . $row[$col];
            } else {
                $noms[$col] = null;
            }
        }
        return $noms;
    }

    private function sectionsAvecNoms(): array
    {
        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );
        foreach ($sections as &$s) {
            $s['_noms'] = $this->resolveNoms($s);
        }
        return $sections;
    }

    private function federationsAvecNoms(): array
    {
        $feds = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_federations ORDER BY federation'
        );
        foreach ($feds as &$f) {
            $f['_noms'] = $this->resolveNoms($f);
        }
        return $feds;
    }

    private function adherentsDeLaSection(int $idSection): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id_adht, nom_adht, prenom_adht, profession_adht, NIN_adh
             FROM comitemaore_adherent WHERE id_section = ?
             ORDER BY nom_adht, prenom_adht',
            [$idSection]
        );
    }

    private function adherentsDeLaFederation(int $idFederation): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT a.id_adht, a.nom_adht, a.prenom_adht, a.profession_adht, a.NIN_adh, s.section
             FROM comitemaore_adherent a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             WHERE s.id_federation = ?
             ORDER BY a.nom_adht, a.prenom_adht',
            [$idFederation]
        );
    }

    private function log(string $action, string $result, ?int $cible, array $detail): void
    {
        $login = $this->getUser()?->getUserIdentifier();
        $this->logService->log($action, $result, 'organigramme', $cible, $detail, null, $login);
    }
}
