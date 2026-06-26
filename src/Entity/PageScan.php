<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageScanRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One run of the page-availability checker for a site: the pages found in its sitemap and the HTTP
 * status each returned. Kept as history so the owner can track availability over time.
 */
#[ORM\Entity(repositoryClass: PageScanRepository::class)]
class PageScan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column]
    private DateTimeImmutable $scannedAt;

    /** Human-readable note, e.g. when no sitemap was found. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private int $totalPages = 0;

    #[ORM\Column]
    private int $okCount = 0;

    #[ORM\Column]
    private int $errorCount = 0;

    /** @var Collection<int, PageResult> */
    #[ORM\OneToMany(targetEntity: PageResult::class, mappedBy: 'pageScan', cascade: ['persist'], orphanRemoval: true)]
    private Collection $results;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->scannedAt = new DateTimeImmutable();
        $this->results = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getScannedAt(): DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getOkCount(): int
    {
        return $this->okCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /** @return Collection<int, PageResult> */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(PageResult $result): static
    {
        if (!$this->results->contains($result)) {
            $this->results->add($result);
            ++$this->totalPages;
            if ($result->isOk()) {
                ++$this->okCount;
            } else {
                ++$this->errorCount;
            }
        }

        return $this;
    }
}
