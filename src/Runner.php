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


	public function Run($settingName = null)
	{
		$runn = $settingName ?
			[$this->settings[$settingName]] :
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
		$processor = new $settingProcessor['class'];
		InjectExtension::callInjects($this->di, $processor);
		if (isset($settingProcessor['setup'])) {
			foreach ($settingProcessor['setup'] as list($method, $arguments)) {
				$processor->{$method}(...$arguments);
			}
		}
		if ($processor instanceof IProcessor) {
			foreach ($processor->getParametersClasses() as $class) {
				$parameter = new $class();
				InjectExtension::callInjects($this->di, $parameter);
				$this->registerParameter($parameter);
			}
		}

		return $processor;
	}


	public function registerParameter(IParameter $parameter)
	{
		$this->parameters[$parameter->getName()] = $parameter;
	}


	protected function getSourceData($settingSource)
	{
		$source = new $settingSource['class'];
		if (!($source instanceof ISource)) {
			throw new \LogicException();
		}
		InjectExtension::callInjects($this->di, $source);
		if (isset($settingSource['setup'])) {
			foreach ($settingSource['setup'] as list($method, $arguments)) {
				$source->{$method}(...$arguments);
			}
		}

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