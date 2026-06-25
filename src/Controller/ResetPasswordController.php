<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\Mail\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Display & process the form to request a password reset.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, Mailer $mailer, UserRepository $userRepository): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail((string) $form->get('email')->getData(), $mailer, $userRepository);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Confirmation page after a reset request was submitted (always reachable, no e-mail leak).
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        if (!$this->canCheckEmail()) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('reset_password/check_email.html.twig', [
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
        ]);
    }

    /**
     * Validate the token and process the new password.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, ?string $token = null): Response
    {
        if (null !== $token) {
            // Move the token out of the URL and into the session to avoid leaking it (referrer, logs).
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun jeton de réinitialisation trouvé dans l\'URL ou la session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf('Lien invalide ou expiré : %s', $e->getReason()));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        if (!$user instanceof User) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A token can only be used once.
            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Mot de passe réinitialisé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, Mailer $mailer, UserRepository $userRepository): RedirectResponse
    {
        $user = $userRepository->findOneBy(['email' => $emailFormData]);

        // Mark the check-email page reachable regardless, so we never reveal whether the account exists.
        $this->setCanCheckEmailInSession();

        if ($user instanceof User) {
            try {
                $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                $mailer->sendPasswordReset($user, $resetToken);
            } catch (ResetPasswordExceptionInterface) {
                // e.g. a reset was already requested recently — stay silent, no information leak.
            }
        }

        return $this->redirectToRoute('app_check_email');
    }
}
