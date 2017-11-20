<?php


namespace Lossik\Runner;


class AbstractParameter implements IParameter
{


	public function getName()
	{
		return 'abstract';
	}


	public function getParametersItems($sourceData)
	{
		return [];
	}


}