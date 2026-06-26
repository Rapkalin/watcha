<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Technology;
use App\Repository\SiteRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A website monitored by its owner. Its technology and version are detected by scanning the URL.
 */
#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 512)]
    #[Assert\NotBlank]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
    #[Assert\Length(max: 512)]
    private string $url = '';

    /** Technology found by the automatic scan. */
    #[ORM\Column(length: 20, nullable: true, enumType: Technology::class)]
    private ?Technology $technology = null;

    /** Version string found by the automatic scan (e.g. "10.3.1"); null when not exposed. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $detectedVersion = null;

    /**
     * Technology set manually by the owner. Takes precedence over the auto-detected one but is
     * kept alongside it so both can be displayed.
     */
    #[ORM\Column(length: 20, nullable: true, enumType: Technology::class)]
    private ?Technology $manualTechnology = null;

    /**
     * Version set manually by the owner. Takes precedence over the auto-detected one for CVE
     * matching, while the auto-detected value keeps being refreshed by scans. Useful for
     * Symfony/Laravel, whose version is not exposed publicly.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $manualVersion = null;

    /** Latest stable version known for the detected technology at scan time. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $latestKnownVersion = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastScannedAt = null;

    /** Human-readable note about the last scan outcome (e.g. why detection failed). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastScanMessage = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, SiteAlert> */
    #[ORM\OneToMany(targetEntity: SiteAlert::class, mappedBy: 'site', orphanRemoval: true)]
    private Collection $alerts;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->alerts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = rtrim($url, '/');

        return $this;
    }

    public function getTechnology(): ?Technology
    {
        return $this->technology;
    }

    public function setTechnology(?Technology $technology): static
    {
        $this->technology = $technology;

        return $this;
    }

    public function getDetectedVersion(): ?string
    {
        return $this->detectedVersion;
    }

    public function setDetectedVersion(?string $detectedVersion): static
    {
        $this->detectedVersion = $detectedVersion;

        return $this;
    }

    public function getManualTechnology(): ?Technology
    {
        return $this->manualTechnology;
    }

    public function setManualTechnology(?Technology $manualTechnology): static
    {
        $this->manualTechnology = $manualTechnology;

        return $this;
    }

    public function getManualVersion(): ?string
    {
        return $this->manualVersion;
    }

    public function setManualVersion(?string $manualVersion): static
    {
        $this->manualVersion = (null === $manualVersion || '' === trim($manualVersion)) ? null : trim($manualVersion);

        return $this;
    }

    /** Whether the owner has overridden the technology or version manually. */
    public function hasManualOverride(): bool
    {
        return null !== $this->manualVersion || null !== $this->manualTechnology;
    }

    /** Technology used everywhere (manual override wins over auto-detection). */
    public function getEffectiveTechnology(): ?Technology
    {
        return $this->manualTechnology ?? $this->technology;
    }

    /** Version used for CVE matching (manual override wins over auto-detection). */
    public function getEffectiveVersion(): ?string
    {
        return $this->manualVersion ?? $this->detectedVersion;
    }

    public function getLatestKnownVersion(): ?string
    {
        return $this->latestKnownVersion;
    }

    public function setLatestKnownVersion(?string $latestKnownVersion): static
    {
        $this->latestKnownVersion = $latestKnownVersion;

        return $this;
    }

    public function getLastScannedAt(): ?DateTimeImmutable
    {
        return $this->lastScannedAt;
    }

    public function setLastScannedAt(?DateTimeImmutable $lastScannedAt): static
    {
        $this->lastScannedAt = $lastScannedAt;

        return $this;
    }

    public function getLastScanMessage(): ?string
    {
        return $this->lastScanMessage;
    }

    public function setLastScanMessage(?string $lastScanMessage): static
    {
        $this->lastScanMessage = $lastScanMessage;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, SiteAlert> */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function isUpdateAvailable(): bool
    {
        $current = $this->getEffectiveVersion();

        return null !== $current
            && null !== $this->latestKnownVersion
            && $current !== $this->latestKnownVersion;
    }
}
