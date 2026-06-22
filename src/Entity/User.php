<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_MAINTAINER = 'ROLE_MAINTAINER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /**
     * The roles granted to the user beyond the implicit ROLE_USER.
     *
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $displayName = null;

    /**
     * A self-registered account must be approved by a maintainer/admin before login.
     */
    #[ORM\Column]
    private bool $approved = false;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, Site> */
    #[ORM\OneToMany(targetEntity: Site::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $sites;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->sites = new ArrayCollection();
    }

    /** @return Collection<int, Site> */
    public function getSites(): Collection
    {
        return $this->sites;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual, non-guaranteed-unique identifier for the user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        // ROLE_USER is implicit, never persist it.
        $this->roles = array_values(array_filter($roles, static fn (string $r) => self::ROLE_USER !== $r));

        return $this;
    }

    public function getHighestRole(): string
    {
        return match (true) {
            in_array(self::ROLE_ADMIN, $this->roles, true) => self::ROLE_ADMIN,
            in_array(self::ROLE_MAINTAINER, $this->roles, true) => self::ROLE_MAINTAINER,
            default => self::ROLE_USER,
        };
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getApprovedBy(): ?self
    {
        return $this->approvedBy;
    }

    public function approve(self $approver): static
    {
        $this->approved = true;
        $this->approvedAt = new DateTimeImmutable();
        $this->approvedBy = $approver;

        return $this;
    }

    public function revokeApproval(): static
    {
        $this->approved = false;
        $this->approvedAt = null;
        $this->approvedBy = null;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // No temporary, plain-text sensitive data stored on the entity.
    }
}
