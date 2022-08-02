<?php

namespace Lossik\Runner;

class AbstractProcessor implements IProcessor
{

	/**
	 * @return array<string>
	 */
	public function getParameters(): array
	{
		return [];
	}

	/**
	 * @return array<string>
	 */
	public function getParametersClasses(): array
	{
		return [];
	}

	/**
	 * @param IParameter $parameter
	 * @return void
	 */
	public function setupParameter(IParameter $parameter): void
	{

	}

	/**
	 * @param array<mixed> $parameters
	 * @return void
	 */
	public function process(array $parameters): void
	{

	}

}