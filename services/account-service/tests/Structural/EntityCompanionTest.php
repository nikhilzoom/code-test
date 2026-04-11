<?php

declare(strict_types=1);

namespace App\Tests\Structural;

use PHPUnit\Framework\TestCase;

// Feature: fund-transfer-system, Property 1: Every entity has companion repository interface, repository implementation, and factory

class EntityCompanionTest extends TestCase
{
    /** @var string */
    private string $srcPath;

    /**
     * Set up the source path for the service.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->srcPath = __DIR__ . '/../../src';
    }

    /**
     * Assert that every entity class has a corresponding repository interface, repository, and factory.
     *
     * @return void
     */
    public function testEveryEntityHasCompanionClasses(): void
    {
        $entityPath = $this->srcPath . '/Entity';

        if (!is_dir($entityPath)) {
            $this->markTestSkipped('No Entity directory found.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $entityCount = 0;
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $entityName = $file->getBasename('.php');
            $entityCount++;

            $repositoryInterface = $this->srcPath . '/Repository/' . $entityName . 'RepositoryInterface.php';
            $repository          = $this->srcPath . '/Repository/' . $entityName . 'Repository.php';
            $factory             = $this->srcPath . '/Factory/' . $entityName . 'Factory.php';

            $this->assertFileExists(
                $repositoryInterface,
                "Expected {$entityName}RepositoryInterface.php to exist for entity {$entityName}"
            );
            $this->assertFileExists(
                $repository,
                "Expected {$entityName}Repository.php to exist for entity {$entityName}"
            );
            $this->assertFileExists(
                $factory,
                "Expected {$entityName}Factory.php to exist for entity {$entityName}"
            );
        }

        $this->assertGreaterThan(0, $entityCount, 'Expected at least one entity to be found.');
    }
}
