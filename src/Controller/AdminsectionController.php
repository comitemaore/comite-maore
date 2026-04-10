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
#[Route('/admin-sections')]
class AdminsectionController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LogService $logService,
    ) {}

    // ------------------------------------------------------------------
    // LISTE de tous les admins par section
    // ------------------------------------------------------------------
    #[Route('', name: 'app_admin_section_list')]
    public function list(): Response
    {
        $admins = $this->connection->fetchAllAssociative(
            "SELECT a.*, s.section AS nom_section
             FROM comitemaore_admin a
             LEFT JOIN comitemaore_sections s ON a.id_section = s.id_section
             ORDER BY s.section, a.rang"
        );

        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );

        // Regrouper par section
        $grouped = [];
        foreach ($admins as $adm) {
            $grouped[$adm['id_section']]['nom'] = $adm['nom_section'];
            $grouped[$adm['id_section']]['admins'][] = $adm;
        }

        return $this->render('admin_section/list.html.twig', [
            'grouped'  => $grouped,
            'sections' => $sections,
            'total'    => count($admins),
        ]);
    }

    // ------------------------------------------------------------------
    // AJOUTER un admin
    // ------------------------------------------------------------------
    #[Route('/ajouter', name: 'app_admin_section_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );
        $error = null;

        if ($request->isMethod('POST')) {
            $idSection  = $request->request->getInt('id_section');
            $login      = trim($request->request->get('login_admin', ''));
            $nom        = trim($request->request->get('nom_admin', ''));
            $prenom     = trim($request->request->get('prenom_admin', ''));
            $email      = trim($request->request->get('email_admin', ''));
            $rang       = $request->request->getInt('rang', 1);

            // Vérifier max 3 admins par section
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM comitemaore_admin WHERE id_section = ? AND actif = 1',
                [$idSection]
            );
            if ($count >= 3) {
                $error = 'Cette section a déjà 3 administrateurs (maximum autorisé).';
            }
            // Vérifier unicité du login
            elseif ($this->connection->fetchOne(
                'SELECT COUNT(*) FROM comitemaore_admin WHERE login_admin = ?', [$login]
            ) > 0) {
                $error = "Le login « $login » est déjà utilisé par un autre administrateur.";
            }
            // Vérifier que le rang n'est pas déjà pris
            elseif ($this->connection->fetchOne(
                'SELECT COUNT(*) FROM comitemaore_admin WHERE id_section = ? AND rang = ?',
                [$idSection, $rang]
            ) > 0) {
                $error = "Le rang $rang est déjà attribué dans cette section.";
            } else {
                $this->connection->insert('comitemaore_admin', [
                    'id_section'   => $idSection,
                    'login_admin'  => $login,
                    'nom_admin'    => $nom,
                    'prenom_admin' => $prenom,
                    'email_admin'  => $email ?: null,
                    'rang'         => $rang,
                    'actif'        => 1,
                ]);

                $this->log('add_admin', 'succes', (int) $this->connection->lastInsertId(),
                    ['login' => $login, 'section' => $idSection]);
                $this->addFlash('success', "Administrateur « $login » ajouté à la section.");
                return $this->redirectToRoute('app_admin_section_list');
            }
        }

        return $this->render('admin_section/form.html.twig', [
            'sections' => $sections,
            'admin'    => [],
            'mode'     => 'ajouter',
            'error'    => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // MODIFIER un admin
    // ------------------------------------------------------------------
    #[Route('/{id}/modifier', name: 'app_admin_section_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $admin = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_admin WHERE id_admin = ?', [$id]
        );
        if (!$admin) throw $this->createNotFoundException();

        $sections = $this->connection->fetchAllAssociative(
            'SELECT * FROM comitemaore_sections ORDER BY section'
        );
        $error = null;

        if ($request->isMethod('POST')) {
            $idSection = $request->request->getInt('id_section');
            $login     = trim($request->request->get('login_admin', ''));
            $nom       = trim($request->request->get('nom_admin', ''));
            $prenom    = trim($request->request->get('prenom_admin', ''));
            $email     = trim($request->request->get('email_admin', ''));
            $rang      = $request->request->getInt('rang', 1);
            $actif     = $request->request->getInt('actif', 1);

            // Vérifier unicité login (sauf lui-même)
            $loginExists = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM comitemaore_admin WHERE login_admin = ? AND id_admin != ?',
                [$login, $id]
            );
            if ($loginExists > 0) {
                $error = "Le login « $login » est déjà utilisé.";
            }
            // Vérifier rang dans la section (sauf lui-même)
            elseif ($this->connection->fetchOne(
                'SELECT COUNT(*) FROM comitemaore_admin WHERE id_section = ? AND rang = ? AND id_admin != ?',
                [$idSection, $rang, $id]
            ) > 0) {
                $error = "Le rang $rang est déjà attribué dans cette section.";
            } else {
                $this->connection->update('comitemaore_admin', [
                    'id_section'   => $idSection,
                    'login_admin'  => $login,
                    'nom_admin'    => $nom,
                    'prenom_admin' => $prenom,
                    'email_admin'  => $email ?: null,
                    'rang'         => $rang,
                    'actif'        => $actif,
                ], ['id_admin' => $id]);

                $this->log('edit_admin', 'succes', $id, ['login' => $login]);
                $this->addFlash('success', "Administrateur « $login » modifié.");
                return $this->redirectToRoute('app_admin_section_list');
            }
        }

        return $this->render('admin_section/form.html.twig', [
            'sections' => $sections,
            'admin'    => $admin,
            'mode'     => 'modifier',
            'error'    => $error,
        ]);
    }

    // ------------------------------------------------------------------
    // DÉSACTIVER / RÉACTIVER
    // ------------------------------------------------------------------
    #[Route('/{id}/toggle', name: 'app_admin_section_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_admin_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $admin = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_admin WHERE id_admin = ?', [$id]
        );
        if (!$admin) throw $this->createNotFoundException();

        $newActif = $admin['actif'] ? 0 : 1;
        $this->connection->update('comitemaore_admin', ['actif' => $newActif], ['id_admin' => $id]);

        $this->log('toggle_admin', 'succes', $id, [
            'login' => $admin['login_admin'],
            'actif' => $newActif,
        ]);
        $this->addFlash('success', sprintf(
            'Administrateur « %s » %s.',
            $admin['login_admin'],
            $newActif ? 'réactivé' : 'désactivé'
        ));

        return $this->redirectToRoute('app_admin_section_list');
    }

    // ------------------------------------------------------------------
    // SUPPRIMER
    // ------------------------------------------------------------------
    #[Route('/{id}/supprimer', name: 'app_admin_section_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('del_admin_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $admin = $this->connection->fetchAssociative(
            'SELECT * FROM comitemaore_admin WHERE id_admin = ?', [$id]
        );
        if (!$admin) throw $this->createNotFoundException();

        $this->connection->delete('comitemaore_admin', ['id_admin' => $id]);
        $this->log('delete_admin', 'succes', $id, ['login' => $admin['login_admin']]);
        $this->addFlash('success', "Administrateur « {$admin['login_admin']} » supprimé.");

        return $this->redirectToRoute('app_admin_section_list');
    }

    private function log(string $action, string $result, ?int $cible, array $detail = []): void
    {
        $login = $this->getUser()?->getUserIdentifier();
        $this->logService->log($action, $result, 'admin_section', $cible, $detail, null, $login);
    }
}
