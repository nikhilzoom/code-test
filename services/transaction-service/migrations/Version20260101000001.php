<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the `transactions` table for the Transaction Service.
 *
 * Schema:
 *   id                     INT AUTO_INCREMENT PRIMARY KEY
 *   source_account_id      INT NOT NULL
 *   destination_account_id INT NOT NULL
 *   amount                 DECIMAL(15,2) NOT NULL
 *   currency               CHAR(3) NOT NULL
 *   status                 VARCHAR(20) NOT NULL  (pending|completed|failed)
 *   created_at             DATETIME NOT NULL
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
        return 'Create transactions table';
    }

    /**
     * Apply the migration — create the transactions table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS transactions (
                id                     INT           NOT NULL AUTO_INCREMENT,
                source_account_id      INT           NOT NULL,
                destination_account_id INT           NOT NULL,
                amount                 DECIMAL(15,2) NOT NULL,
                currency               VARCHAR(3)    NOT NULL,
                status                 VARCHAR(20)   NOT NULL,
                created_at             DATETIME      NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    /**
     * Reverse the migration — drop the transactions table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS transactions');
    }
}
