<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Lossik\Runner\Runner;
use Lossik\Runner\Tests\Fixtures\ArraySource;
use Lossik\Runner\Tests\Fixtures\IncludedProcessor;
use Lossik\Runner\Tests\Fixtures\RecordingProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

final class IncludeTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingProcessor::reset();
    }

    protected function tearDown(): void
    {
        ContainerFactory::cleanup();
    }

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function includes(): array
    {
        return [
            'directory with trailing slash' => ["['{dir}/inc/']", ['jobInc', 'main']],
            'directory without trailing slash' => ["['{dir}/inc']", ['jobInc', 'main']],
            'file' => ["['{dir}/single.neon']", ['jobFile', 'main']],
            'file with upper-case extension' => ["['{dir}/ext/x.NEON']", ['jobUpper', 'main']],
            'string instead of list' => ["'{dir}/single.neon'", ['jobFile', 'main']],
            'directory from %parameter%' => ["['%incDir%/']", ['jobInc', 'main']],
            'only *.neon files, extension case-insensitive' => ["['{dir}/ext/']", ['jobA1', 'jobUpper', 'main']],
            'missing path is skipped' => ["['{dir}/missing/']", ['main']],
            'nested include from included file' => ["['{dir}/nest.neon']", ['jobFile', 'jobNest', 'main']],
        ];
    }

    /**
     * @param array<int, string> $expectedJobs
     */
    #[DataProvider('includes')]
    public function testIncludedJobsAreLoaded(string $include, array $expectedJobs): void
    {
        $dir = ContainerFactory::tempDir();
        mkdir($dir . '/inc');
        file_put_contents($dir . '/inc/a.neon', $this->job('jobInc'));
        file_put_contents($dir . '/single.neon', $this->job('jobFile'));
        mkdir($dir . '/ext');
        $extFiles = ['aneon' => 'jobNoDot', 'x.NEON' => 'jobUpper', 'x.neon.bak' => 'jobBak', 'a.neon' => 'jobA1'];
        foreach ($extFiles as $file => $job) {
            file_put_contents($dir . '/ext/' . $file, $this->job($job));
        }
        file_put_contents($dir . '/nest.neon', "include: ['" . $dir . "/single.neon']\n" . $this->job('jobNest'));

        $neon = $this->neon(
            '    include: ' . str_replace('{dir}', $dir, $include) . "\n" . $this->job('main', '    '),
            $dir . '/inc',
        );
        $jobs = ContainerFactory::create($neon)->getByType(Runner::class)->getProcessors();

        sort($jobs);
        $this->assertSame($expectedJobs, $jobs);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function includesOfTwoIncludedFiles(): array
    {
        return [
            'string and string' => ["'{dir}/x.neon'", "'{dir}/y.neon'"],
            'list and string' => ["['{dir}/x.neon']", "'{dir}/y.neon'"],
            'string and list' => ["'{dir}/x.neon'", "['{dir}/y.neon']"],
        ];
    }

    #[DataProvider('includesOfTwoIncludedFiles')]
    public function testIncludesOfTwoIncludedFilesAreAllLoaded(string $includeA, string $includeB): void
    {
        $dir = ContainerFactory::tempDir();
        file_put_contents($dir . '/x.neon', $this->job('jobX'));
        file_put_contents($dir . '/y.neon', $this->job('jobY'));
        file_put_contents(
            $dir . '/a.neon',
            'include: ' . str_replace('{dir}', $dir, $includeA) . "\n" . $this->job('jobA'),
        );
        file_put_contents(
            $dir . '/b.neon',
            'include: ' . str_replace('{dir}', $dir, $includeB) . "\n" . $this->job('jobB'),
        );
        $neon = $this->neon(
            "    include: ['" . $dir . "/a.neon', '" . $dir . "/b.neon']\n" . $this->job('main', '    '),
        );

        $jobs = ContainerFactory::create($neon)->getByType(Runner::class)->getProcessors();

        sort($jobs);
        $this->assertSame(['jobA', 'jobB', 'jobX', 'jobY', 'main'], $jobs);
    }

    public function testSameFileIncludedTwiceUnderDifferentPathsIsMergedOnce(): void
    {
        $dir = ContainerFactory::tempDir();
        mkdir($dir . '/sub');
        file_put_contents($dir . '/inc.neon', "jobA:\n    processor:\n        setup: [[setTag, [inc]]]\n");
        $neon = $this->neon(
            "    include: ['" . $dir . "/inc.neon', '" . $dir . "/sub/../inc.neon']\n"
            . "    jobA:\n        processor:\n"
            . '            class: ' . RecordingProcessor::class . "\n"
            . "            setup: [[setTag, [main]]]\n"
            . '        source: {class: ' . ArraySource::class . "}\n",
        );

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('jobA');

        $this->assertSame(['main', 'inc'], RecordingProcessor::$tags);
    }

    public function testParameterInIncludedFileStaysLiteral(): void
    {
        $dir = ContainerFactory::tempDir();
        file_put_contents(
            $dir . '/inc.neon',
            "jobP:\n    processor:\n        class: " . RecordingProcessor::class
            . "\n        setup: [[setTag, ['%incDir%']]]\n"
            . '    source: {class: ' . ArraySource::class . "}\n",
        );
        $neon = $this->neon("    include: ['" . $dir . "/inc.neon']\n" . $this->job('main', '    '), $dir);

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('jobP');

        $this->assertSame(['%incDir%'], RecordingProcessor::$tags);
    }

    public function testIncludeOfOtherTypeThanStringOrListThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        ContainerFactory::create($this->neon("    include: 5\n" . $this->job('main', '    ')));
    }

    public function testIncludedJobOverridesMainAndSetupListsAreConcatenated(): void
    {
        $file = ContainerFactory::tempDir() . '/inc.neon';
        file_put_contents(
            $file,
            "jobA:\n    processor:\n        class: " . IncludedProcessor::class
            . "\n        setup: [[setTag, [inc]]]\n",
        );
        $neon = "extensions:\n    runner: Lossik\Runner\RunnerExtension\n\n"
            . "runner:\n    include: ['" . $file . "']\n    jobA:\n        processor:\n"
            . '            class: ' . RecordingProcessor::class . "\n"
            . "            setup: [[setTag, [main]]]\n"
            . '        source: {class: ' . ArraySource::class . "}\n";

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('jobA');

        $this->assertSame([IncludedProcessor::class, IncludedProcessor::class], RecordingProcessor::$classes);
        $this->assertSame(['main', 'inc'], RecordingProcessor::$tags);
    }

    private function job(string $name, string $indent = ''): string
    {
        return $indent . $name . ":\n" . $indent . '    processor: {class: ' . RecordingProcessor::class . "}\n";
    }

    private function neon(string $runner, ?string $incDir = null): string
    {
        return ($incDir !== null ? "parameters:\n    incDir: '" . $incDir . "'\n\n" : '')
            . "extensions:\n    runner: Lossik\Runner\RunnerExtension\n\n"
            . "runner:\n" . $runner;
    }
}
