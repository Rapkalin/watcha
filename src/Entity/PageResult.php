<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The HTTP status of a single page within a {@see PageScan}. A null status code means the page
 * could not be reached at all (timeout, DNS, connection refused, blocked address).
 */
#[ORM\Entity]
class PageResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PageScan $pageScan;

    #[ORM\Column(length: 1024)]
    private string $url;

    #[ORM\Column(nullable: true)]
    private ?int $statusCode;

    public function __construct(PageScan $pageScan, string $url, ?int $statusCode)
    {
        $this->pageScan = $pageScan;
        $this->url = $url;
        $this->statusCode = $statusCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPageScan(): PageScan
    {
        return $this->pageScan;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /** A page is considered healthy on any 2xx response. */
    public function isOk(): bool
    {
        return null !== $this->statusCode && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
