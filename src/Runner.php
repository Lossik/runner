<?php


namespace Lossik\Runner;


use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;

class Runner
{


	/** @var Container @inject */
	public $di;
	protected $settings;

	/** @var  IParameter[] */
	protected $parameters;


	public function __construct($settings)
	{
		$this->settings = $settings;
	}


	public function getProcessors()
	{
		return $this->settings ? array_keys($this->settings) : [];
	}


	public function Run($processorName = null)
	{
		$runn = $processorName ?
			[$this->settings[$processorName]] :
			$this->settings;

		foreach ($runn as $setting) {
			$processor  = $this->createProcessor($setting['processor']);
			$sourceData = isset($setting['source']) ? $this->getSourceData($setting['source']) : [];
			if ($processor instanceof IProcessor) {
				$parameters = $this->getParameters($sourceData, $processor->getParameters());
				$processor->process($parameters);
			}
			else {
				$parameters = $this->getParameters($sourceData, self::ifset($setting, 'parameters', []));
				$processor->{$setting['processor']['call']}($parameters);
			}
		}
	}


	protected function createProcessor($settingProcessor)
	{
		$service = self::ifset($settingProcessor, 'service', false);
		if ($service) {
			return $this->di->createService($service);
		}
		$class     = self::ifset($settingProcessor, 'class');
		$setup     = self::ifset($settingProcessor, 'setup', []);
		$processor = $this->create($class, $setup);
		if ($processor instanceof IProcessor) {
			foreach ($processor->getParametersClasses() as $parametersClass) {
				$parameter = $this->create($parametersClass, [], IParameter::class);
				$processor->setupParameter($parameter);
				$this->registerParameter($parameter);
			}
		}

		return $processor;
	}


	private static function ifset($array, $path, $default = null, $pathDelimiter = '/')
	{
		foreach ($p = explode($pathDelimiter, $path) as $key) {
			if (isset($array[$key])) {
				$array = $array[$key];
			}
			else {
				return $default;
			}
		}

		return $array;
	}


	protected function create($class, $setup = [], $instanceof = null)
	{
		$object = new $class;
		if ($instanceof) {
			if (!($object instanceof $instanceof)) {
				throw new \LogicException();
			}
		}
		InjectExtension::callInjects($this->di, $object);
		foreach ($setup as list($method, $arguments)) {
			$object->{$method}(...$arguments);
		}

		return $object;
	}


	public function registerParameter(IParameter $parameter)
	{
		$this->parameters[$parameter->getName()] = $parameter;
	}


	protected function getSourceData($settingSource)
	{
		$class = self::ifset($settingSource, 'class', AbstractSource::class);
		$setup = self::ifset($settingSource, 'setup', []);
		/** @var ISource $source */
		$source = $this->create($class, $setup, ISource::class);

		return $source->getSourceData();
	}


	protected function getParameters($sourceData, $parameters)
	{
		$result = [];
		foreach ($parameters as $parameter) {
			$parameterName = $this->parameters[$parameter]->getName();
			$items         = $this->parameters[$parameter]->getParametersItems($sourceData);
			foreach ($items as $parameter_id => $item) {
				$result[$parameter_id][$parameterName] = $item;
			}
		}

		return $result;
	}


	public function RunWidthSourceData($processorName, $sourceData)
	{
		$setting   = $this->settings[$processorName];
		$processor = $this->createProcessor($setting['processor']);
		if ($processor instanceof IProcessor) {
			$parameters = $this->getParameters($sourceData, $processor->getParameters());
			$processor->process($parameters);
		}
		else {
			$parameters = $this->getParameters($sourceData, self::ifset($setting, 'parameters', []));
			$processor->{self::ifset($setting, 'processor/call')}($parameters);
		}
	}

}