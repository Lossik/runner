<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

use Lossik\Runner\ISource;

final class ArraySource implements ISource
{
    public function getSourceData(): array
    {
        return [['id' => 1, 'v' => 'a'], ['id' => 2, 'v' => 'b']];
    }
}
