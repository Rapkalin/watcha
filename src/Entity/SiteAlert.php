<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AlertType;
use App\Enum\Severity;
use App\Repository\SiteAlertRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * An actionable alert raised for a monitored site: either a matching CVE or an available update.
 */
#[ORM\Entity(repositoryClass: SiteAlertRepository::class)]
#[ORM\Table(name: 'site_alert')]
#[ORM\UniqueConstraint(name: 'uniq_alert_dedup', columns: ['site_id', 'type', 'dedup_key'])]
class SiteAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 30, enumType: AlertType::class)]
    private AlertType $type;

    /** Stable key (CVE id or target version) used to avoid duplicating alerts across scans. */
    #[ORM\Column(length: 100)]
    private string $dedupKey;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Advisory $advisory = null;

    #[ORM\Column(length: 20, enumType: Severity::class)]
    private Severity $severity = Severity::UNKNOWN;

    #[ORM\Column(length: 255)]
    private string $message = '';

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column]
    private bool $acknowledged = false;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    public function __construct(Site $site, AlertType $type, string $dedupKey)
    {
        $this->site = $site;
        $this->type = $type;
        $this->dedupKey = $dedupKey;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getType(): AlertType
    {
        return $this->type;
    }

    public function getDedupKey(): string
    {
        return $this->dedupKey;
    }

    public function getAdvisory(): ?Advisory
    {
        return $this->advisory;
    }

    public function setAdvisory(?Advisory $advisory): static
    {
        $this->advisory = $advisory;

        return $this;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function setSeverity(Severity $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = mb_substr($message, 0, 255);

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function resolve(): static
    {
        $this->resolved = true;
        $this->resolvedAt = new DateTimeImmutable();

        return $this;
    }

    public function reopen(): static
    {
        $this->resolved = false;
        $this->resolvedAt = null;

        return $this;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function setAcknowledged(bool $acknowledged): static
    {
        $this->acknowledged = $acknowledged;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
