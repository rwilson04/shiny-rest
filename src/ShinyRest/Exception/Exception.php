<?php

namespace ShinyRest\Exception;

/*
 * Custom exception parent class to facilitate api+problem error display
 */
class Exception extends \Exception 
	implements ExceptionInterface
{
	protected $_httpStatus;
	protected $_details;
	public function __construct($message = null, $code=0, \Exception $previous=null, $httpStatus=500, $details=null)
	{
		$this->_httpStatus=$httpStatus;
		if ($details !== null)
		{
			$this->_details=$details;
		}
		parent::__construct($message, $code, $previous);
	}
	public function getHttpStatus()
	{
		return $this->_httpStatus;
	}
	public function getDetails()
	{
		return $this->_details;
	}
}
