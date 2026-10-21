<?php

declare(strict_types=1);

namespace Lossik\Runner\Tests\Fixtures;

use Lossik\Runner\AbstractParameter;

final class UpperParameter extends AbstractParameter
{
    public function getName(): string
    {
        return 'v';
    }

    public function getParametersItems(array $sourceData): array
    {
        $items = [];
        foreach ($sourceData as $row) {
            $items[$row['id']] = strtoupper($row['v']);
        }

        return $items;
    }
}
