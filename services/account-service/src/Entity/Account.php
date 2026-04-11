<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Account entity representing a financial account owned by a user.
 *
 * Mapped to the `accounts` MySQL table via Doctrine ORM.
 *
 * @ORM\Entity
 * @ORM\Table(name="accounts")
 *
 * @package App\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
class Account
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
     * The ID of the user who owns this account.
     *
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Column(type: 'integer')]
    private int $userId;

    /**
     * The current balance of the account stored as a decimal string.
     *
     * Stored as a string to avoid floating-point precision issues.
     *
     * @ORM\Column(type="decimal", precision=18, scale=4)
     *
     * @var string
     */
    #[ORM\Column(type: 'decimal', precision: 18, scale: 4)]
    private string $balance;

    /**
     * The ISO 4217 currency code for this account (e.g. "USD", "EUR").
     *
     * @ORM\Column(type="string", length=3)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    /**
     * The timestamp when the account record was created.
     *
     * @ORM\Column(type="datetime_immutable")
     *
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Get the account's primary key identifier.
     *
     * @return int The account ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the account's primary key identifier.
     *
     * @param int $id The account ID to set.
     *
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the ID of the user who owns this account.
     *
     * @return int The owner user ID.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Set the ID of the user who owns this account.
     *
     * @param int $userId The user ID to set.
     *
     * @return void
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the current balance of the account as a decimal string.
     *
     * @return string The account balance.
     */
    public function getBalance(): string
    {
        return $this->balance;
    }

    /**
     * Set the current balance of the account.
     *
     * @param string $balance The balance to set as a decimal string.
     *
     * @return void
     */
    public function setBalance(string $balance): void
    {
        $this->balance = $balance;
    }

    /**
     * Get the ISO 4217 currency code for this account.
     *
     * @return string The 3-character currency code.
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set the ISO 4217 currency code for this account.
     *
     * @param string $currency The 3-character currency code to set.
     *
     * @return void
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * Get the creation timestamp of the account record.
     *
     * @return \DateTimeImmutable The creation date/time.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp of the account record.
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
