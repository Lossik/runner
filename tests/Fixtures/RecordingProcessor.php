<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

use Lossik\Runner\AbstractProcessor;
use Lossik\Runner\IParameter;

class RecordingProcessor extends AbstractProcessor
{
    /** @var array<int, array<mixed>> */
    public static array $log = [];

    /** @var array<int, string> */
    public static array $tags = [];

    /** @var array<int, string> */
    public static array $classes = [];

    public static function reset(): void
    {
        self::$log = [];
        self::$tags = [];
        self::$classes = [];
    }

    public function setTag(string $tag): void
    {
        self::$tags[] = $tag;
        self::$classes[] = static::class;
    }

    public function getParameters(): array
    {
        return ['v'];
    }

    public function getParametersClasses(): array
    {
        return [UpperParameter::class];
    }

    public function setupParameter(IParameter $parameter): void
    {
        self::$log[] = ['setup'];
    }

    public function process(array $parameters): void
    {
        self::$log[] = ['process', $parameters];
    }
}
