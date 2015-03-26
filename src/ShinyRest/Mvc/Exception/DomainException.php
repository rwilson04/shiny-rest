<?php

namespace ShinyRest\Mvc\Exception;

class DomainException extends \ShinyRest\Exception\DomainException
    implements ExceptionInterface
{
    public function getHttpStatus() {
        return 500;
    }

    public function getDetails() {
    }
}
