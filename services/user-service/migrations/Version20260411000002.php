<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the `password` column to the `users` table.
 *
 * The column stores a bcrypt-hashed password string.
 * DEFAULT '' is used to avoid breaking existing rows during migration;
 * all new users will have a proper hash set via the registration flow.
 */
final class Version20260411000002 extends AbstractMigration
{
    /**
     * Returns a description of what this migration does.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Add password column to users table';
    }

    /**
     * Apply the migration — add the password column.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER email"
        );
    }

    /**
     * Reverse the migration — drop the password column.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN password');
    }
}
