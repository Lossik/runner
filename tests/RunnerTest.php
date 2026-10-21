<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use LogicException;
use Lossik\Runner\Runner;
use Lossik\Runner\Tests\Fixtures\ArraySource;
use Lossik\Runner\Tests\Fixtures\NoParamClassProcessor;
use Lossik\Runner\Tests\Fixtures\RecordingProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function containerSetups(): array
    {
        return [
            'A: no decorator' => [false, true],
            'B: decorator inject: true' => [true, true],
            'C: no InjectExtension' => [false, false],
        ];
    }

    #[DataProvider('containerSetups')]
    public function testRunPassesParametersToProcessor(bool $decorator, bool $injectExtension): void
    {
        $neon = $this->neon(
            '    jobA:' . "\n"
            . '        processor: {class: ' . RecordingProcessor::class . '}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n",
            $decorator,
        );

        ContainerFactory::create($neon, $injectExtension)->getByType(Runner::class)->Run('jobA');

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

    public function testUnregisteredParameterThrowsLogicExceptionNamingIt(): void
    {
        $neon = $this->neon(
            '    job2:' . "\n"
            . '        processor: {class: ' . NoParamClassProcessor::class . '}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n",
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/\bv\b/');

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('job2');
    }

    public function testServiceProcessorGetsItsParameters(): void
    {
        $neon = $this->neon(
            '    jobSvc:' . "\n"
            . '        processor: {service: procSvc}' . "\n"
            . '        source: {class: ' . ArraySource::class . '}' . "\n",
            true,
            '    procSvc: ' . RecordingProcessor::class . "\n",
        );

        ContainerFactory::create($neon)->getByType(Runner::class)->Run('jobSvc');

        $this->assertSame([['setup'], ['process', self::PAYLOAD]], RecordingProcessor::$log);
    }

    private function neon(string $jobs, bool $decorator = true, string $services = ''): string
    {
        return 'extensions:' . "\n" . '    runner: Lossik\\Runner\\RunnerExtension' . "\n\n"
            . ($decorator
                ? 'decorator:' . "\n" . '    Lossik\\Runner\\Runner:' . "\n" . '        inject: true' . "\n\n"
                : '')
            . ($services !== '' ? 'services:' . "\n" . $services . "\n" : '')
            . 'runner:' . "\n" . $jobs;
    }
}
