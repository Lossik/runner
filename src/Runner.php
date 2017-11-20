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
				$parameters = $this->getParameters($sourceData, isset($setting['parameters']) ? $setting['parameters'] : []);
				$processor->{$setting['processor']['call']}($parameters);
			}
		}
	}


	protected function createProcessor($settingProcessor)
	{
		$processor = $this->create(self::ifset($settingProcessor, 'class'), self::ifset($settingProcessor, 'setup', []));
		if ($processor instanceof IProcessor) {
			foreach ($processor->getParametersClasses() as $class) {
				$parameter = $this->create($class, [], IParameter::class);
				$processor->setupParameter($parameter);
				$this->registerParameter($parameter);
			}
		}

		return $processor;
	}


	protected function create($class, $setup = [], $instanceof = null)
	{
		var_dump($class);
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


	public function registerParameter(IParameter $parameter)
	{
		$this->parameters[$parameter->getName()] = $parameter;
	}


	protected function getSourceData($settingSource)
	{
		/** @var ISource $source */
		$source = $this->create(self::ifset($settingSource, 'class'), self::ifset($settingSource, 'setup', []), ISource::class);

		return $source->getSourceData();
	}


	protected function getParameters($sourceData, $parameters)
	{
		$result = [];
		foreach ($parameters as $parameter) {
			$parameterName = $this->parameters[$parameter]->getName();
			$items         = $this->parameters[$parameter]->getParametersItems($sourceData);
			foreach ($items as $contract_id => $item) {
				$result[$contract_id][$parameterName] = $item;
			}
		}

		return $result;
	}

}