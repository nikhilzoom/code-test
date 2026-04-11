<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the `ledger_entries` table for the Ledger Service.
 *
 * Schema:
 *   id             INT AUTO_INCREMENT PRIMARY KEY
 *   transaction_id INT NOT NULL
 *   account_id     INT NOT NULL
 *   entry_type     VARCHAR(6) NOT NULL  (debit|credit)
 *   amount         DECIMAL(15,2) NOT NULL
 *   created_at     DATETIME NOT NULL
 */
final class Version20260101000001 extends AbstractMigration
{
    /**
     * Returns a description of what this migration does.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create ledger_entries table';
    }

    /**
     * Apply the migration — create the ledger_entries table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS ledger_entries (
                id             INT           NOT NULL AUTO_INCREMENT,
                transaction_id INT           NOT NULL,
                account_id     INT           NOT NULL,
                entry_type     VARCHAR(6)    NOT NULL,
                amount         DECIMAL(15,2) NOT NULL,
                created_at     DATETIME      NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    /**
     * Reverse the migration — drop the ledger_entries table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ledger_entries');
    }
}
