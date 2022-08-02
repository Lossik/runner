<?php

namespace Lossik\Runner;

interface IProcessor
{

	/**
	 * @return array<string>
	 */
	public function getParameters(): array;

	/**
	 * @return array<string>
	 */
	public function getParametersClasses(): array;

	/**
	 * @param IParameter $parameter
	 */
	public function setupParameter(IParameter $parameter): void;

	/**
	 * @param array<mixed> $parameters
	 * @return void
	 */
	public function process(array $parameters): void;

}