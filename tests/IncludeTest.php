<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Lossik\Runner\Runner;
use Lossik\Runner\Tests\Fixtures\ArraySource;
use Lossik\Runner\Tests\Fixtures\IncludedProcessor;
use Lossik\Runner\Tests\Fixtures\RecordingProcessor;
use PHPUnit\Framework\TestCase;

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
}
