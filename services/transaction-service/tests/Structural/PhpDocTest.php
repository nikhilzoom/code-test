<?php

declare(strict_types=1);

namespace App\Tests\Structural;

use PHPUnit\Framework\TestCase;

// Feature: fund-transfer-system, Property 3: All public classes, methods, and properties carry PHPDoc blocks

class PhpDocTest extends TestCase
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
     * Assert that all public classes discovered under src/ have a PHPDoc block.
     *
     * @return void
     */
    public function testAllPublicClassesHavePhpDoc(): void
    {
        $classes = $this->discoverClasses($this->srcPath);
        $this->assertGreaterThan(0, count($classes), 'Expected at least one class to be found.');

        foreach ($classes as $className) {
            $reflection = new \ReflectionClass($className);
            $docComment = $reflection->getDocComment();

            $this->assertNotFalse(
                $docComment,
                "Class {$className} is missing a PHPDoc block."
            );
            $this->assertNotEmpty(
                trim($docComment),
                "Class {$className} has an empty PHPDoc block."
            );
        }
    }

    /**
     * Assert that all public methods declared in src/ classes have a PHPDoc block.
     *
     * @return void
     */
    public function testAllPublicMethodsHavePhpDoc(): void
    {
        $classes = $this->discoverClasses($this->srcPath);

        foreach ($classes as $className) {
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $docComment = $method->getDocComment();

                $this->assertNotFalse(
                    $docComment,
                    "Method {$className}::{$method->getName()}() is missing a PHPDoc block."
                );
                $this->assertNotEmpty(
                    trim($docComment),
                    "Method {$className}::{$method->getName()}() has an empty PHPDoc block."
                );
            }
        }
    }

    /**
     * Assert that all public properties declared in src/ classes have a PHPDoc block.
     *
     * @return void
     */
    public function testAllPublicPropertiesHavePhpDoc(): void
    {
        $classes = $this->discoverClasses($this->srcPath);

        foreach ($classes as $className) {
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $docComment = $property->getDocComment();

                $this->assertNotFalse(
                    $docComment,
                    "Property {$className}::\${$property->getName()} is missing a PHPDoc block."
                );
                $this->assertNotEmpty(
                    trim($docComment),
                    "Property {$className}::\${$property->getName()} has an empty PHPDoc block."
                );
            }
        }
    }

    /**
     * Discover all PHP classes and interfaces under the given path.
     *
     * @param string $path The root directory to scan.
     *
     * @return string[] Array of fully qualified class/interface names.
     */
    private function discoverClasses(string $path): array
    {
        $classes = [];

        if (!is_dir($path)) {
            return $classes;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

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

            if (class_exists($className) || interface_exists($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * Extract the fully qualified class or interface name from PHP source content.
     *
     * @param string $content The PHP file content.
     *
     * @return string|null The fully qualified name, or null if not found.
     */
    private function extractFullyQualifiedClassName(string $content): ?string
    {
        $namespace = null;
        $className = null;

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/^(?:class|interface|abstract\s+class)\s+(\w+)/m', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        return $namespace ? $namespace . '\\' . $className : $className;
    }
}
