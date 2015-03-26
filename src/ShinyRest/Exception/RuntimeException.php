<?php

namespace ShinyRest\Exception;

class RuntimeException extends \RuntimeException
    implements ExceptionInterface
{
    protected $httpStatus;
    protected $details;
    public function __construct($message = null, $code=0, \Exception $previous=null, $httpStatus=500, $details=null) {
        $this->httpStatus=$httpStatus;
        if ($details !== null) {
            $this->details=$details;
        }
        parent::__construct($message, $code, $previous);
    }

    public function getHttpStatus() {
        return $this->httpStatus;
    }

    public function getDetails() {
        return $this->details;
    }
}
