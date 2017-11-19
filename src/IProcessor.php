<?php


namespace Lossik\Runner;


interface IProcessor
{

	public function getParameters();

	public function getParametersClasses();

	public function process($parameters);

}