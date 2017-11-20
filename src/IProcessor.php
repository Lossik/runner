<?php


namespace Lossik\Runner;


interface IProcessor
{

	public function getParameters();

	public function getParametersClasses();

	public function setupParameter(IParameter $parameter);

	public function process($parameters);

}