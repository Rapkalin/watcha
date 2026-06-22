<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Severity;
use App\Enum\Technology;
use App\Repository\AdvisoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A security advisory (CVE/GHSA/SA) imported from an external feed for one technology.
 */
#[ORM\Entity(repositoryClass: AdvisoryRepository::class)]
#[ORM\Table(name: 'advisory')]
#[ORM\UniqueConstraint(name: 'uniq_source_external', columns: ['source', 'external_id'])]
#[ORM\Index(name: 'idx_technology', columns: ['technology'])]
class Advisory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: Technology::class)]
    private Technology $technology;

    /** Feed the advisory came from, e.g. "osv.dev" or "wordpress.org". */
    #[ORM\Column(length: 40)]
    private string $source;

    /** Stable identifier within the source (GHSA id, CVE id, SA id...). */
    #[ORM\Column(length: 100)]
    private string $externalId;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cveId = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 20, enumType: Severity::class)]
    private Severity $severity = Severity::UNKNOWN;

    /**
     * Composer-style version constraint of affected versions (e.g. ">=6.0,<6.4.10").
     * Used to decide whether a detected version is impacted.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $affectedConstraint = null;

    /** First fixed version, when published by the source. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fixedVersion = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $referenceUrl = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $importedAt;

    public function __construct(Technology $technology, string $source, string $externalId)
    {
        $this->technology = $technology;
        $this->source = $source;
        $this->externalId = $externalId;
        $this->importedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTechnology(): Technology
    {
        return $this->technology;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCveId(): ?string
    {
        return $this->cveId;
    }

    public function setCveId(?string $cveId): static
    {
        $this->cveId = $cveId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = mb_substr($title, 0, 255);

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

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

    public function getAffectedConstraint(): ?string
    {
        return $this->affectedConstraint;
    }

    public function setAffectedConstraint(?string $affectedConstraint): static
    {
        $this->affectedConstraint = $affectedConstraint;

        return $this;
    }

    public function getFixedVersion(): ?string
    {
        return $this->fixedVersion;
    }

    public function setFixedVersion(?string $fixedVersion): static
    {
        $this->fixedVersion = $fixedVersion;

        return $this;
    }

    public function getReferenceUrl(): ?string
    {
        return $this->referenceUrl;
    }

    public function setReferenceUrl(?string $referenceUrl): static
    {
        $this->referenceUrl = $referenceUrl;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getImportedAt(): DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function touchImported(): void
    {
        $this->importedAt = new DateTimeImmutable();
    }
}
