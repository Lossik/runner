<?php

namespace Lossik\Runner;

class AbstractSource implements ISource
{

	/**
	 * @return array<mixed>
	 */
	public function getSourceData(): array
	{
		return [];
	}

}