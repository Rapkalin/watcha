<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Repository\SiteAlertRepository;
use App\Service\Mail\Mailer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sends one digest e-mail per owner for their new (never-notified, still open) alerts, then stamps
 * each alert as notified. Driven by the scan command — not by web-triggered scans — so e-mails are
 * sent in a batch and never block an interactive request. An alert is only marked notified when its
 * digest was actually handed to the transport, so a transient SMTP failure is retried next scan.
 */
final class AlertNotifier
{
    public function __construct(
        private readonly SiteAlertRepository $alertRepository,
        private readonly Mailer $mailer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return int the number of alerts for which a notification was sent
     */
    public function notifyPending(): int
    {
        $alerts = $this->alertRepository->findUnnotifiedOpen();
        if ([] === $alerts) {
            return 0;
        }

        // Group by owner so each one receives a single digest.
        $byOwner = [];
        foreach ($alerts as $alert) {
            $owner = $alert->getSite()->getOwner();
            $byOwner[$owner->getId()]['owner'] = $owner;
            $byOwner[$owner->getId()]['alerts'][] = $alert;
        }

        $notified = 0;
        foreach ($byOwner as $group) {
            if (!$this->mailer->sendAlertDigest($group['owner'], $group['alerts'])) {
                continue;
            }
            foreach ($group['alerts'] as $alert) {
                $alert->markNotified();
                ++$notified;
            }
        }

        $this->em->flush();

        return $notified;
    }
}
