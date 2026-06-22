<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User administration.
 *
 * - MAINTAINER: can view the list and approve pending accounts (no other modification).
 * - ADMIN: can additionally change roles, revoke approval and delete accounts.
 */
#[Route('/admin/users')]
#[IsGranted(User::ROLE_MAINTAINER)]
final class UserController extends AbstractController
{
    private const ASSIGNABLE_ROLES = [
        'Basic' => '',
        'Maintainer' => User::ROLE_MAINTAINER,
        'Admin' => User::ROLE_ADMIN,
    ];

    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAllOrdered(),
            'pending' => $userRepository->findPendingApproval(),
            'assignable_roles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_user_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Request $request, User $user, UserRepository $userRepository): Response
    {
        $this->validateCsrf($request, 'approve'.$user->getId());

        /** @var User $approver */
        $approver = $this->getUser();
        $user->approve($approver);
        $userRepository->save($user);

        $this->addFlash('success', sprintf('Compte "%s" approuvé.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/role', name: 'app_admin_user_role', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function changeRole(Request $request, User $user, UserRepository $userRepository): Response
    {
        $this->validateCsrf($request, 'role'.$user->getId());

        $role = (string) $request->request->get('role', '');
        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            $this->addFlash('error', 'Rôle invalide.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        // Never let the last remaining admin demote themselves out of existence.
        $isLastAdmin = $user === $this->getUser()
            && User::ROLE_ADMIN !== $role
            && $userRepository->countApprovedAdmins() <= 1;
        if ($isLastAdmin) {
            $this->addFlash('error', 'Impossible de retirer le dernier administrateur.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        $user->setRoles('' === $role ? [] : [$role]);
        $userRepository->save($user);

        $this->addFlash('success', sprintf('Rôle de "%s" mis à jour.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/revoke', name: 'app_admin_user_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function revoke(Request $request, User $user, UserRepository $userRepository): Response
    {
        $this->validateCsrf($request, 'revoke'.$user->getId());

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas révoquer votre propre accès.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        $user->revokeApproval();
        $userRepository->save($user);

        $this->addFlash('success', sprintf('Accès de "%s" révoqué.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function delete(Request $request, User $user, UserRepository $userRepository): Response
    {
        $this->validateCsrf($request, 'delete'.$user->getId());

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        $userRepository->remove($user);
        $this->addFlash('success', 'Compte supprimé.');

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function validateCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
