<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests;

use Lossik\Runner\Runner;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testRunnerListsConfiguredProcessors(): void
    {
        $this->assertSame(['a'], (new Runner(['a' => []]))->getProcessors());
    }
}
