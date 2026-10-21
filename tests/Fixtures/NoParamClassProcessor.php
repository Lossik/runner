<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

final class NoParamClassProcessor extends RecordingProcessor
{
    public function getParametersClasses(): array
    {
        return [];
    }
}
