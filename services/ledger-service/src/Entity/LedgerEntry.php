<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LedgerEntry entity representing an immutable financial ledger record.
 *
 * Each entry captures one leg of a fund transfer — either a debit or a credit —
 * linking a transaction to a specific account. Mapped to the `ledger_entries`
 * MySQL table via Doctrine ORM.
 *
 * @ORM\Entity
 * @ORM\Table(name="ledger_entries")
 *
 * @package App\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
class LedgerEntry
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
     * The ID of the transaction this entry belongs to.
     *
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Column(type: 'integer')]
    private int $transactionId;

    /**
     * The ID of the account affected by this ledger entry.
     *
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Column(type: 'integer')]
    private int $accountId;

    /**
     * The type of ledger entry: either "debit" or "credit".
     *
     * @ORM\Column(type="string", length=6)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 6)]
    private string $entryType;

    /**
     * The monetary amount of this ledger entry stored as a decimal string.
     *
     * Stored as a string to avoid floating-point precision issues.
     *
     * @ORM\Column(type="decimal", precision=15, scale=2)
     *
     * @var string
     */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount;

    /**
     * The timestamp when this ledger entry was created.
     *
     * @ORM\Column(type="datetime_immutable")
     *
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Get the ledger entry's primary key identifier.
     *
     * @return int The ledger entry ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the ledger entry's primary key identifier.
     *
     * @param int $id The ledger entry ID to set.
     *
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the ID of the transaction this entry belongs to.
     *
     * @return int The transaction ID.
     */
    public function getTransactionId(): int
    {
        return $this->transactionId;
    }

    /**
     * Set the ID of the transaction this entry belongs to.
     *
     * @param int $transactionId The transaction ID to set.
     *
     * @return void
     */
    public function setTransactionId(int $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /**
     * Get the ID of the account affected by this ledger entry.
     *
     * @return int The account ID.
     */
    public function getAccountId(): int
    {
        return $this->accountId;
    }

    /**
     * Set the ID of the account affected by this ledger entry.
     *
     * @param int $accountId The account ID to set.
     *
     * @return void
     */
    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }

    /**
     * Get the type of this ledger entry ("debit" or "credit").
     *
     * @return string The entry type.
     */
    public function getEntryType(): string
    {
        return $this->entryType;
    }

    /**
     * Set the type of this ledger entry.
     *
     * @param string $entryType The entry type to set ("debit" or "credit").
     *
     * @return void
     */
    public function setEntryType(string $entryType): void
    {
        $this->entryType = $entryType;
    }

    /**
     * Get the monetary amount of this ledger entry as a decimal string.
     *
     * @return string The amount.
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * Set the monetary amount of this ledger entry.
     *
     * @param string $amount The amount to set as a decimal string.
     *
     * @return void
     */
    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * Get the creation timestamp of this ledger entry.
     *
     * @return \DateTimeImmutable The creation date/time.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp of this ledger entry.
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
