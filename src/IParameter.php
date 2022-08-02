<?php

namespace Lossik\Runner;

interface IParameter
{

	public function getName(): string;

	/**
	 * @param array<mixed> $sourceData
	 * @return array<mixed>
	 */
	public function getParametersItems(array $sourceData): array;

}