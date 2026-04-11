<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the `users` table for the User Service.
 *
 * Schema:
 *   id         INT AUTO_INCREMENT PRIMARY KEY
 *   name       VARCHAR(100) NOT NULL
 *   email      VARCHAR(255) NOT NULL UNIQUE
 *   created_at DATETIME NOT NULL
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
        return 'Create users table';
    }

    /**
     * Apply the migration — create the users table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS users (
                id         INT          NOT NULL AUTO_INCREMENT,
                name       VARCHAR(100) NOT NULL,
                email      VARCHAR(255) NOT NULL,
                created_at DATETIME     NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY (id),
                UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    /**
     * Reverse the migration — drop the users table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
