<?php


namespace Lossik\Runner;


class AbstractProcessor implements IProcessor
{


	public function getParameters()
	{
		return [];
	}


	public function getParametersClasses()
	{
		return [];
	}


	public function setupParameter(IParameter $parameter)
	{

	}


	public function process($parameters)
	{

	}


}