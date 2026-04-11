<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;

/**
 * Contract for sending transactional notifications to account owners.
 *
 * Implementations may deliver notifications via SMTP, HTTP to a dedicated
 * notification microservice, a message queue, or any other transport.
 *
 * All implementations MUST be fire-and-forget: failures must be handled
 * internally and must never propagate exceptions to the caller.
 *
 * @package App\Service
 */
interface NotificationServiceInterface
{
    /**
     * Notify the account owner that their account has been debited.
     *
     * Must not throw under any circumstances. Failures should be logged
     * internally and silently suppressed.
     *
     * @param Account $account The account that was debited (with updated balance).
     * @param string  $amount  The amount that was debited as a decimal string.
     *
     * @return void
     */
    public function notifyDebit(Account $account, string $amount): void;

    /**
     * Notify the account owner that their account has been credited.
     *
     * Must not throw under any circumstances. Failures should be logged
     * internally and silently suppressed.
     *
     * @param Account $account The account that was credited (with updated balance).
     * @param string  $amount  The amount that was credited as a decimal string.
     *
     * @return void
     */
    public function notifyCredit(Account $account, string $amount): void;
}
