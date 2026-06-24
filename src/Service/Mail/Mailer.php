<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\SiteAlert;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Central place for the application's transactional e-mails. Every send is wrapped so that a mail
 * transport failure is logged but never breaks the user-facing action that triggered it.
 *
 * The "From" address is applied globally by config/packages/mailer.yaml, so messages only set the
 * recipient, subject, template and context here.
 */
final class Mailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sends the e-mail ownership confirmation link to a freshly registered user.
     */
    public function sendEmailVerification(User $user, string $signedUrl): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getDisplayName() ?? ''))
            ->subject('Watcha — confirmez votre adresse e-mail')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'displayName' => $user->getDisplayName(),
                'verifyUrl' => $signedUrl,
            ]);

        $this->send($email, 'e-mail verification');
    }

    /**
     * Tells the approvers (maintainers/admins) that a new account is waiting for validation.
     *
     * @param list<string> $recipientEmails
     */
    public function notifyPendingRegistration(array $recipientEmails, User $pendingUser): void
    {
        if ([] === $recipientEmails) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to(...$recipientEmails)
            ->subject('Watcha — nouveau compte à valider')
            ->htmlTemplate('emails/registration_pending.html.twig')
            ->context([
                'pendingEmail' => $pendingUser->getEmail(),
                'pendingVerified' => $pendingUser->isEmailVerified(),
                'adminUrl' => $this->urlGenerator->generate('app_admin_user_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->send($email, 'pending registration notification');
    }

    /**
     * Tells a user their account has been approved and they can now sign in.
     */
    public function notifyAccountApproved(User $user): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getDisplayName() ?? ''))
            ->subject('Watcha — votre compte est activé')
            ->htmlTemplate('emails/account_approved.html.twig')
            ->context([
                'displayName' => $user->getDisplayName(),
                'loginUrl' => $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->send($email, 'account approval notification');
    }

    /**
     * Sends a digest of new alerts (CVEs and available updates) to a site owner.
     *
     * @param SiteAlert[] $alerts all belonging to sites owned by $owner
     *
     * @return bool true if the e-mail was handed to the transport, false on failure
     */
    public function sendAlertDigest(User $owner, array $alerts): bool
    {
        if ([] === $alerts) {
            return false;
        }

        // Group by site for a readable digest.
        $groups = [];
        foreach ($alerts as $alert) {
            $site = $alert->getSite();
            $groups[$site->getId()]['site'] = $site;
            $groups[$site->getId()]['alerts'][] = $alert;
        }

        $email = (new TemplatedEmail())
            ->to(new Address($owner->getEmail(), $owner->getDisplayName() ?? ''))
            ->subject(sprintf('Watcha — %d alerte(s) sur vos sites', count($alerts)))
            ->htmlTemplate('emails/alert_digest.html.twig')
            ->context([
                'displayName' => $owner->getDisplayName(),
                'alertCount' => count($alerts),
                'groups' => array_values($groups),
                'dashboardUrl' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        return $this->send($email, 'alert digest');
    }

    private function send(TemplatedEmail $email, string $description): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            // Never let a mail failure bubble up into the triggering request/command.
            $this->logger->error(sprintf('Failed to send %s: %s', $description, $e->getMessage()), ['exception' => $e]);

            return false;
        }
    }
}
