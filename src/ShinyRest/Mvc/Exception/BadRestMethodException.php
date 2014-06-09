<?php

namespace ShinyRest\Mvc\Exception;

class BadRestMethodException extends BadMethodCallException
	implements ExceptionInterface
{
	//override default code and status
	public function __construct($message = null, $code=101006, 
		\Exception $previous=null, $httpStatus=405, $details=null)
	{
		if ($code === null)
		{
			$code = 101006;
		}
		if ($httpStatus === null)
		{
			$httpStatus = 405;
		}
		$resolution = "Change either REST method or HTTP method, or both";
		$details = array_merge($details, compact('resolution'));
		parent::__construct($message, $code, $previous, $httpStatus, $details);
	}
}
