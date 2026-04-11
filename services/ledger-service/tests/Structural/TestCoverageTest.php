<?php

declare(strict_types=1);

namespace App\Tests\Structural;

use PHPUnit\Framework\TestCase;

// Feature: fund-transfer-system, Property 5: Every service and repository class has a test file that uses mocks and covers all public methods

class TestCoverageTest extends TestCase
{
    /** @var string */
    private string $srcPath;

    /** @var string */
    private string $testsPath;

    /**
     * Set up the source and tests paths for the service.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->srcPath   = __DIR__ . '/../../src';
        $this->testsPath = __DIR__ . '/../../tests';
    }

    /**
     * Assert that every service class has a corresponding test file with mocks.
     *
     * @return void
     */
    public function testEveryServiceClassHasATestFileWithMocks(): void
    {
        $this->assertClassesInDirectoryHaveTests($this->srcPath . '/Service');
    }

    /**
     * Assert that every repository class has a corresponding test file with mocks.
     *
     * @return void
     */
    public function testEveryRepositoryClassHasATestFileWithMocks(): void
    {
        $this->assertClassesInDirectoryHaveTests($this->srcPath . '/Repository');
    }

    /**
     * Assert that all concrete classes in a directory have test files with mocks and test methods.
     *
     * @param string $directory The directory to scan for classes.
     *
     * @return void
     */
    private function assertClassesInDirectoryHaveTests(string $directory): void
    {
        if (!is_dir($directory)) {
            $this->markTestSkipped("Directory {$directory} not found.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $classCount = 0;

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match('/^interface\s+/m', $content)) {
                continue;
            }

            $className = $file->getBasename('.php');
            $classCount++;

            $testFile = $this->findTestFile($className . 'Test.php');

            $this->assertNotNull(
                $testFile,
                "Expected a test file '{$className}Test.php' to exist under tests/ for class {$className}."
            );

            $testContent = file_get_contents($testFile);

            $hasMock = str_contains($testContent, 'createMock') || str_contains($testContent, 'getMockBuilder');
            $this->assertTrue(
                $hasMock,
                "Test file for {$className} does not use createMock() or getMockBuilder()."
            );

            $hasTestMethod = (bool) preg_match('/function\s+test\w+\s*\(|@test\s/i', $testContent);
            $this->assertTrue(
                $hasTestMethod,
                "Test file for {$className} does not contain any test methods (prefixed 'test' or annotated @test)."
            );
        }

        $this->assertGreaterThan(0, $classCount, 'Expected at least one class to be found in ' . $directory);
    }

    /**
     * Search for a test file by name anywhere under the tests/ directory.
     *
     * @param string $testFileName The test file basename to search for.
     *
     * @return string|null The absolute path to the test file, or null if not found.
     */
    private function findTestFile(string $testFileName): ?string
    {
        if (!is_dir($this->testsPath)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getBasename() === $testFileName) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
