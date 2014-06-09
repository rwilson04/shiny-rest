<?php

namespace ShinyRest\Mvc;

use Zend\View\Model\ViewModel;

/*
 * For initialization (interface injection) during instantiation
 */
interface RestViewModelAwareInterface
{
	public function setXmlModel(ViewModel $model);
	public function setJsonModel(ViewModel $model);
	public function getXmlModel();
	public function getJsonModel();

}
