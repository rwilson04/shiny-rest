<?php

namespace ShinyRest\Mvc\Exception;

class InvalidJsonException extends \ShinyRest\Mvc\Exception\InvalidArgumentException
	implements ExceptionInterface
{
	public function __construct($message="Invalid JSON in request", $code=101012, \Exception $previous=null, $httpStatus=400, $details=array('detail'=>'Content-Type header indicates application/json, but unable to parse data as JSON.', 'resolution'=>'Change Content-Type header to the correct type, or make sure data is properly formatted.'))
	{
		parent::__construct($message, $code, $previous, $httpStatus, $details);
	}
}
