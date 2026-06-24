<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->remove($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to rehash a password transparently when the algorithm/cost changes.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user);
    }

    /**
     * @return User[]
     */
    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.approved = false')
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.approved', 'ASC')
            ->addOrderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts approved admins. Roles live in a JSON column, so we filter in PHP to stay
     * portable (no MySQL-specific JSON_CONTAINS DQL function required).
     */
    public function countApprovedAdmins(): int
    {
        return count(array_filter(
            $this->findApprovedWithRole(),
            static fn (User $u) => in_array(User::ROLE_ADMIN, $u->getRoles(), true),
        ));
    }

    /**
     * E-mail addresses of the approved maintainers/admins, i.e. the people who can approve a
     * pending account. Used to notify them of new self-registrations.
     *
     * @return list<string>
     */
    public function findApproverEmails(): array
    {
        $approvers = array_filter(
            $this->findApprovedWithRole(),
            static fn (User $u) => in_array(User::ROLE_MAINTAINER, $u->getRoles(), true)
                || in_array(User::ROLE_ADMIN, $u->getRoles(), true),
        );

        return array_values(array_map(static fn (User $u) => $u->getEmail(), $approvers));
    }

    /**
     * All approved users. Roles live in a JSON column, so callers filter on roles in PHP to
     * stay portable (no MySQL-specific JSON_CONTAINS DQL function required).
     *
     * @return User[]
     */
    private function findApprovedWithRole(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.approved = true')
            ->getQuery()
            ->getResult();
    }
}
