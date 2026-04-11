<?php

declare(strict_types=1);

namespace App\Tests\Structural;

use PHPUnit\Framework\TestCase;

// Feature: fund-transfer-system, Property 4: Controller route methods include required PHPDoc tags

class ControllerDocTest extends TestCase
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
     * Assert that all controller route methods have required PHPDoc tags.
     *
     * @return void
     */
    public function testControllerRouteMethodsHaveRequiredPhpDocTags(): void
    {
        $controllerPath = $this->srcPath . '/Controller';

        if (!is_dir($controllerPath)) {
            $this->markTestSkipped('No Controller directory found.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $routeMethodCount = 0;

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

            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                if (!$this->methodHasRouteAnnotation($method)) {
                    continue;
                }

                $routeMethodCount++;
                $docComment = $method->getDocComment();

                $this->assertNotFalse(
                    $docComment,
                    "Route method {$className}::{$method->getName()}() is missing a PHPDoc block."
                );

                $this->assertMatchesRegularExpression(
                    '/@return\s+/',
                    $docComment,
                    "Route method {$className}::{$method->getName()}() is missing a @return tag."
                );

                $hasParam      = (bool) preg_match('/@param\s+/', $docComment);
                $hasNoParamNote = (bool) preg_match('/no\s+param|no\s+input|no\s+request\s+param/i', $docComment);
                $this->assertTrue(
                    $hasParam || $hasNoParamNote,
                    "Route method {$className}::{$method->getName()}() is missing a @param tag or note about no parameters."
                );

                $descriptionMatch = preg_match('/\/\*\*\s*\n\s*\*\s+([^@\s][^\n]+)/m', $docComment, $descMatches);
                $this->assertTrue(
                    (bool) $descriptionMatch,
                    "Route method {$className}::{$method->getName()}() is missing a prose description in its PHPDoc."
                );
            }
        }

        $this->assertGreaterThan(0, $routeMethodCount, 'Expected at least one @Route method to be found in controllers.');
    }

    /**
     * Determine whether a method carries a Route annotation or attribute.
     *
     * @param \ReflectionMethod $method The method to inspect.
     *
     * @return bool True if the method has a Route annotation or attribute.
     */
    private function methodHasRouteAnnotation(\ReflectionMethod $method): bool
    {
        foreach ($method->getAttributes() as $attribute) {
            if (str_contains($attribute->getName(), 'Route')) {
                return true;
            }
        }

        $docComment = $method->getDocComment();
        if ($docComment && str_contains($docComment, '@Route')) {
            return true;
        }

        return false;
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
