<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User entity representing a registered user in the system.
 *
 * Mapped to the `users` MySQL table via Doctrine ORM.
 *
 * @ORM\Entity
 * @ORM\Table(name="users")
 *
 * @package App\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    /**
     * The unique auto-generated primary key.
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    /**
     * The full name of the user (max 100 characters).
     *
     * @ORM\Column(type="string", length=100)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    /**
     * The unique email address of the user (max 255 characters).
     *
     * @ORM\Column(type="string", length=255, unique=true)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    /**
     * The bcrypt-hashed password for the user account.
     *
     * @ORM\Column(type="string", length=255)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $password = '';

    /**
     * The timestamp when the user record was created.
     *
     * @ORM\Column(type="datetime_immutable")
     *
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Get the user's primary key identifier.
     *
     * @return int The user ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the user's primary key identifier.
     *
     * @param int $id The user ID to set.
     *
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the user's full name.
     *
     * @return string The user's name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the user's full name.
     *
     * @param string $name The name to set (max 100 characters).
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the user's email address.
     *
     * @return string The user's email.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set the user's email address.
     *
     * @param string $email The email to set (max 255 characters, must be unique).
     *
     * @return void
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * Get the bcrypt-hashed password for this user account.
     *
     * @return string The hashed password string.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Set the bcrypt-hashed password for this user account.
     *
     * @param string $password The hashed password to store (never plaintext).
     *
     * @return void
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Get the creation timestamp of the user record.
     *
     * @return \DateTimeImmutable The creation date/time.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp of the user record.
     *
     * @param \DateTimeImmutable $createdAt The creation date/time to set.
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
