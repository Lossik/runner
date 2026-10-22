<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

final class OldParameterProcessor extends RecordingProcessor
{
    public function getParametersClasses(): array
    {
        return [OldParameter::class];
    }
}
