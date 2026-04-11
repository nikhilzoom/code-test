<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Transaction entity representing a fund transfer between two accounts.
 *
 * Mapped to the `transactions` MySQL table via Doctrine ORM.
 * Status transitions: pending → completed | failed.
 *
 * @ORM\Entity
 * @ORM\Table(name="transactions")
 *
 * @package App\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
class Transaction
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
     * The ID of the account from which funds are debited.
     *
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Column(type: 'integer')]
    private int $sourceAccountId;

    /**
     * The ID of the account to which funds are credited.
     *
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    #[ORM\Column(type: 'integer')]
    private int $destinationAccountId;

    /**
     * The transfer amount stored as a decimal string to avoid floating-point issues.
     *
     * @ORM\Column(type="decimal", precision=15, scale=2)
     *
     * @var string
     */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount;

    /**
     * The ISO 4217 currency code for this transaction (e.g. "USD", "EUR").
     *
     * @ORM\Column(type="string", length=3)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    /**
     * The current status of the transaction: pending, completed, or failed.
     *
     * @ORM\Column(type="string", length=20)
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    /**
     * The timestamp when the transaction record was created.
     *
     * @ORM\Column(type="datetime_immutable")
     *
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Get the transaction's primary key identifier.
     *
     * @return int The transaction ID.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the transaction's primary key identifier.
     *
     * @param int $id The transaction ID to set.
     *
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the ID of the source account being debited.
     *
     * @return int The source account ID.
     */
    public function getSourceAccountId(): int
    {
        return $this->sourceAccountId;
    }

    /**
     * Set the ID of the source account being debited.
     *
     * @param int $sourceAccountId The source account ID to set.
     *
     * @return void
     */
    public function setSourceAccountId(int $sourceAccountId): void
    {
        $this->sourceAccountId = $sourceAccountId;
    }

    /**
     * Get the ID of the destination account being credited.
     *
     * @return int The destination account ID.
     */
    public function getDestinationAccountId(): int
    {
        return $this->destinationAccountId;
    }

    /**
     * Set the ID of the destination account being credited.
     *
     * @param int $destinationAccountId The destination account ID to set.
     *
     * @return void
     */
    public function setDestinationAccountId(int $destinationAccountId): void
    {
        $this->destinationAccountId = $destinationAccountId;
    }

    /**
     * Get the transfer amount as a decimal string.
     *
     * @return string The transfer amount.
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * Set the transfer amount.
     *
     * @param string $amount The transfer amount as a decimal string.
     *
     * @return void
     */
    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * Get the ISO 4217 currency code for this transaction.
     *
     * @return string The 3-character currency code.
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set the ISO 4217 currency code for this transaction.
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
     * Get the current status of the transaction.
     *
     * @return string One of: pending, completed, failed.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the current status of the transaction.
     *
     * @param string $status One of: pending, completed, failed.
     *
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Get the creation timestamp of the transaction record.
     *
     * @return \DateTimeImmutable The creation date/time.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp of the transaction record.
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
