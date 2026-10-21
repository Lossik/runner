<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Closure;
use Lossik\Runner\Tests\Fixtures\RecordingProcessor;
use Nette\DI\DependencyChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DependencyTest extends TestCase
{
    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        ContainerFactory::cleanup();
    }

    /**
     * @return array<string, array{Closure(string): void, bool}>
     */
    public static function changes(): array
    {
        return [
            'nothing changed' => [static function (string $dir): void {
            }, false],
            'included file changed' => [static function (string $dir): void {
                file_put_contents($dir . '/inc/a.neon', self::job('jobA2'));
            }, true],
            'new .neon file in included directory' => [static function (string $dir): void {
                file_put_contents($dir . '/inc/b.neon', self::job('jobB'));
            }, true],
        ];
    }

    /**
     * @param Closure(string): void $change
     */
    #[DataProvider('changes')]
    public function testContainerExpiresWhenIncludeChanges(Closure $change, bool $expired): void
    {
        $dir = $this->includedDirectory();
        $dependencies = ContainerFactory::compile($this->neon("['" . $dir . "/inc/']"))->exportDependencies();

        $change($dir);
        clearstatcache();

        $this->assertSame($expired, DependencyChecker::isExpired(...$dependencies));
    }

    public function testRelativeIncludeDirectoryStaysValidAfterCwdChange(): void
    {
        $dir = $this->includedDirectory();
        chdir($dir);
        $dependencies = ContainerFactory::compile($this->neon("['inc/']"))->exportDependencies();
        chdir(sys_get_temp_dir());

        $this->assertFalse(DependencyChecker::isExpired(...$dependencies));
    }

    private function includedDirectory(): string
    {
        $dir = ContainerFactory::tempDir();
        mkdir($dir . '/inc');
        file_put_contents($dir . '/inc/a.neon', self::job('jobA'));
        touch($dir . '/inc/a.neon', time() - 100);
        touch($dir . '/inc', time() - 100);
        clearstatcache();

        return $dir;
    }

    private function neon(string $include): string
    {
        return "extensions:\n    runner: Lossik\Runner\RunnerExtension\n\n"
            . "runner:\n    include: " . $include . "\n" . '    main:' . "\n"
            . '        processor: {class: ' . RecordingProcessor::class . "}\n";
    }

    private static function job(string $name): string
    {
        return $name . ":\n    processor: {class: " . RecordingProcessor::class . "}\n";
    }
}
