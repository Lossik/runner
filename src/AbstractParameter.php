<?php

namespace Lossik\Runner;

class AbstractParameter implements IParameter
{

	public function getName(): string
	{
		return 'abstract';
	}

	/**
	 * @param array<mixed> $sourceData
	 * @return array<mixed>
	 */
	public function getParametersItems(array $sourceData): array
	{
		return [];
	}

}