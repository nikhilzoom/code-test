<?php

declare(strict_types=1);

namespace App\Tests\Structural;

use PHPUnit\Framework\TestCase;

// Feature: fund-transfer-system, Property 2: Service classes depend only on repository interfaces

class ServiceDependencyTest extends TestCase
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
     * Assert that all service classes inject repository dependencies via interfaces only.
     *
     * @return void
     */
    public function testServiceClassesDependOnlyOnRepositoryInterfaces(): void
    {
        $servicePath = $this->srcPath . '/Service';

        if (!is_dir($servicePath)) {
            $this->markTestSkipped('No Service directory found.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($servicePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $serviceCount = 0;
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content   = file_get_contents($file->getPathname());
            $className = $this->extractFullyQualifiedClassName($content);

            if ($className === null) {
                continue;
            }

            require_once $file->getPathname();

            if (!class_exists($className)) {
                continue;
            }

            $serviceCount++;
            $reflection  = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                continue;
            }

            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if (!$type instanceof \ReflectionNamedType) {
                    continue;
                }

                $typeName = $type->getName();

                if (stripos($typeName, 'Repository') === false) {
                    continue;
                }

                $this->assertStringEndsWith(
                    'Interface',
                    $typeName,
                    "Service class {$className} has a repository dependency '{$typeName}' that is not an interface. " .
                    "Repository dependencies must be typed against interfaces (ending in 'Interface')."
                );
            }
        }

        $this->assertGreaterThan(0, $serviceCount, 'Expected at least one service class to be found.');
    }

    /**
     * Extract the fully qualified class name from PHP source content.
     *
     * @param string $content The PHP file content.
     *
     * @return string|null The fully qualified class name, or null if not found.
     */
    private function extractFullyQualifiedClassName(string $content): ?string
    {
        $namespace = null;
        $className = null;

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/^class\s+(\w+)/m', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        return $namespace ? $namespace . '\\' . $className : $className;
    }
}
