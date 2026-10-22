<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

use Lossik\Runner\AbstractParameter;

final class OldParameter extends AbstractParameter
{
    public function getName(): string
    {
        return 'v';
    }

    public function getParametersItems(array $sourceData): array
    {
        return [1 => 'old'];
    }
}
