<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\Mail\Mailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
        RateLimiterFactory $registrationLimiter,
        Mailer $mailer,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Honeypot: a filled "website" field means a bot. Mimic success without creating anything.
            if ('' !== trim((string) $form->get('website')->getData())) {
                $this->addFlash('success', 'Compte créé. Un e-mail de confirmation vous a été envoyé : validez votre adresse pour poursuivre.');

                return $this->redirectToRoute('app_login');
            }

            // Throttle genuine sign-ups per client IP to curb mass registrations.
            if (!$registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $this->addFlash('error', "Trop de tentatives d'inscription depuis votre adresse. Réessayez plus tard.");

                return $this->redirectToRoute('app_register');
            }

            // Self-registered users are always "basic" and stay unapproved until validated.
            $user->setRoles([]);
            $user->setApproved(false);
            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
            );
            $userRepository->save($user);

            // Send the e-mail ownership confirmation link. The maintainers/admins are only
            // notified once the address has actually been confirmed (see verifyEmail()).
            $signature = $verifyEmailHelper->generateSignature(
                'app_verify_email',
                (string) $user->getId(),
                $user->getEmail(),
                ['id' => (string) $user->getId()],
            );
            $mailer->sendEmailVerification($user, $signature->getSignedUrl());

            // Inform the approvers right away so they can review/approve, even before the user
            // confirms their address (the e-mail states the verification status).
            $mailer->notifyPendingRegistration($userRepository->findApproverEmails(), $user);

            $this->addFlash('success', 'Compte créé. Un e-mail de confirmation vous a été envoyé : validez votre adresse pour poursuivre.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/verify', name: 'app_verify_email')]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
    ): Response {
        $id = $request->query->get('id');
        $user = null !== $id ? $userRepository->find($id) : null;
        if (null === $user) {
            $this->addFlash('error', 'Lien de validation invalide.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());

            return $this->redirectToRoute('app_login');
        }

        // Confirm the address. The approvers were already notified at registration time.
        if (!$user->isEmailVerified()) {
            $user->verifyEmail();
            $userRepository->save($user);
        }

        $this->addFlash('success', 'Adresse e-mail confirmée. Votre compte doit maintenant être approuvé par un administrateur.');

        return $this->redirectToRoute('app_login');
    }
}
