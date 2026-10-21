<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Lossik\Runner\Runner;
use Lossik\Runner\Tests\Fixtures\ArraySource;
use Lossik\Runner\Tests\Fixtures\NoParamClassProcessor;
use Lossik\Runner\Tests\Fixtures\RecordingProcessor;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    private const PAYLOAD = [1 => ['v' => 'A'], 2 => ['v' => 'B']];

    protected function setUp(): void
    {
        RecordingProcessor::reset();
    }

    protected function tearDown(): void
    {
        ContainerFactory::cleanup();
    }

    public function testRunWithInjectDecoratorPassesParametersToProcessor(): void
    {
        $neon = $this->neon(
            '    jobA:' . "\n"
            . '        processor: {class: ' . RecordingProcessor::class . '}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n",
        );

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('jobA');

        $this->assertSame([['setup'], ['process', self::PAYLOAD]], RecordingProcessor::$log);
    }

    public function testParameterRegistryIsSharedBetweenJobsOfOneRun(): void
    {
        $neon = $this->neon(
            '    job1:' . "\n"
            . '        processor: {class: ' . RecordingProcessor::class . '}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n"
            . '    job2:' . "\n"
            . '        processor: {class: ' . NoParamClassProcessor::class . '}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n",
        );

        ContainerFactory::create($neon)->getByType(Runner::class)->Run();

        $this->assertSame(
            [['setup'], ['process', self::PAYLOAD], ['process', self::PAYLOAD]],
            RecordingProcessor::$log,
        );
    }

    private function neon(string $jobs): string
    {
        return 'extensions:' . "\n" . '    runner: Lossik\\Runner\\RunnerExtension' . "\n\n"
            . 'decorator:' . "\n" . '    Lossik\\Runner\\Runner:' . "\n" . '        inject: true' . "\n\n"
            . 'runner:' . "\n" . $jobs;
    }
}
