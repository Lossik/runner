<?php

namespace Lossik\Runner;

use LogicException;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;

class Runner
{

	/** @inject */
	public Container $di;

	/**
	 * @var array<mixed>
	 */
	protected array $settings;

	/** @var array<IParameter> */
	protected array $parameters;

	public function __construct(array $settings)
	{
		$this->settings = $settings;
	}

	/**
	 * @return array<string>
	 */
	public function getProcessors(): array
	{
		return $this->settings ? array_keys($this->settings) : [];
	}

	/**
	 * @param string|null $processorName
	 * @return void
	 */
	public function Run(?string $processorName = null): void
	{
		$runn = $processorName
			?
			[$this->settings[$processorName]]
			:
			$this->settings;

		foreach ($runn as $setting) {
			$processor = $this->createProcessor($setting['processor']);
			$sourceData = isset($setting['source']) ? $this->getSourceData($setting['source']) : [];
			if ($processor instanceof IProcessor) {
				$parameters = $this->getParameters($sourceData, $processor->getParameters());
				$processor->process($parameters);
			}
		}
	}

	/**
	 * @param array<mixed> $settingProcessor
	 * @return IProcessor
	 */
	protected function createProcessor(array $settingProcessor): IProcessor
	{
		$service = self::ifset($settingProcessor, 'service', false);
		if ($service) {
			$processor = $this->di->createService($service);
			if (!$processor instanceof IProcessor) {
				throw new LogicException('Bad processor config, must be type of IProcessor');
			}

			return $processor;
		}
		$class = self::ifset($settingProcessor, 'class');
		$setup = self::ifset($settingProcessor, 'setup', []);
		$processor = $this->create($class, $setup, IProcessor::class);
		if ($processor instanceof IProcessor) {
			foreach ($processor->getParametersClasses() as $parametersClass) {
				$parameter = $this->create($parametersClass, [], IParameter::class);
				$processor->setupParameter($parameter);
				$this->registerParameter($parameter);
			}
		}

		return $processor;
	}

	/**
	 * @param array<mixed>     $array
	 * @param string           $path
	 * @param mixed|null       $default
	 * @param non-empty-string $pathDelimiter
	 * @return mixed
	 */
	private static function ifset(array $array, string $path, mixed $default = null, string $pathDelimiter = '/'): mixed
	{
		foreach ($p = explode($pathDelimiter, $path) as $key) {
			if (isset($array[$key])) {
				$array = $array[$key];
			} else {
				return $default;
			}
		}

		return $array;
	}

	/**
	 * @template T of object
	 * @param string          $class
	 * @param array<mixed>    $setup
	 * @param class-string<T> $instanceof
	 * @return T
	 */
	protected function create(string $class, array $setup, string $instanceof): object
	{
		$object = new $class;
		if ($instanceof) {
			if (!($object instanceof $instanceof)) {
				throw new LogicException();
			}
		}
		InjectExtension::callInjects($this->di, $object);
		foreach ($setup as [$method, $arguments]) {
			$object->{$method}(...$arguments);
		}

		return $object;
	}

	public function registerParameter(IParameter $parameter): void
	{
		$this->parameters[$parameter->getName()] = $parameter;
	}

	/**
	 * @param array<mixed> $settingSource
	 * @return array<mixed>
	 */
	protected function getSourceData(array $settingSource): array
	{
		$class = self::ifset($settingSource, 'class', AbstractSource::class);
		$setup = self::ifset($settingSource, 'setup', []);
		/** @var ISource $source */
		$source = $this->create($class, $setup, ISource::class);

		return $source->getSourceData();
	}

	/**
	 * @param array<mixed>  $sourceData
	 * @param array<string> $parameters
	 * @return array<mixed>
	 */
	protected function getParameters(array $sourceData, array $parameters): array
	{
		$result = [];
		foreach ($parameters as $parameter) {
			$parameterName = $this->parameters[$parameter]->getName();
			$items = $this->parameters[$parameter]->getParametersItems($sourceData);
			foreach ($items as $parameter_id => $item) {
				$result[$parameter_id][$parameterName] = $item;
			}
		}

		return $result;
	}

	/**
	 * @param string       $processorName
	 * @param array<mixed> $sourceData
	 * @return void
	 */
	public function RunWidthSourceData(string $processorName, array $sourceData): void
	{
		$setting = $this->settings[$processorName];
		$processor = $this->createProcessor($setting['processor']);
		if ($processor instanceof IProcessor) {
			$parameters = $this->getParameters($sourceData, $processor->getParameters());
			$processor->process($parameters);
		}
	}

}