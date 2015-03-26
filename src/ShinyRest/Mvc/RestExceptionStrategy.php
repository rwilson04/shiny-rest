<?php

namespace ShinyRest\Mvc;

use ShinyRest\Mvc\Exception\InvalidJsonException;
use ShinyRest\Mvc\Controller\AbstractRestfulController;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Application;

class RestExceptionStrategy extends AbstractListenerAggregate
{
    /**
     * Attach events
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function attach(EventManagerInterface $events) {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'handleException'), 100);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'handleException'), 100);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'unsetResultException'));

        //$sharedManager     = $events->getSharedManager();
        //$this->listeners[] = $sharedManager->attach('*', 'displayRestError', array($this, 'unsetResultException'));
    }

    /**
     * handle exception
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    public function handleException(MvcEvent $e) {
        //turn of html errors during testing (console) and for curl requests
        $ua = $this->getUserAgent();
        if (APPLICATION_ENV === 'testing' || substr($ua, 0, 4) == "curl") {
            ini_set('html_errors', 0);
        }

        $error = $e->getError();
        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                $this->handleRouteNotFound($e);
                break;
            case Application::ERROR_EXCEPTION:
            case ERROR_CONTROLLER_CANNOT_DISPATCH:
            default:
                $this->formatException($e);
                $this->attachErrorLogger($e);
        }

    }

    /**
     * Get user agent from headers, if it exists
     *
     * @return string
     */
    protected function getUserAgent() {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        } else {
            $ua = "none";
        }
        return $ua;
    }

    /**
     * Get http or https
     *
     * @return string
     */
    protected function getProtocol() {
        $protocol = "http://";
        if (isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] == "on"
        ) {
            $protocol = "https://";
        }
        return $protocol;
    }

    /**
     * If route not found, display a 404 page
     *
     * @param object $e
     *
     * @return void
     */
    protected function handleRouteNotFound($e) {
        $eventManager = $e->getApplication()->getEventManager();
        $sm           = $e->getApplication()->getServiceManager();
        $currentModel = $e->getResult();
        $reason       = "";
        if (is_object($currentModel)) {
            $reason = $currentModel->reason;
        }
        //$error = $e->getParam('error');
        $error = $e->getError();
        if ($reason != "error-router-no-match"
            && $error != "error-router-no-match"
        ) {
            //we are only interested in routing errors here
            return;
        }
        $request      = $e->getRequest();
        $accept       = "";
        $acceptObject = $request->getHeaders()->get('Accept');
        if (is_object($acceptObject)) {
            //no headers found...
            $accept = $acceptObject->toString();
        }
        $uri = $request->getRequestUri();
        if (stristr($accept, 'application/json')
            || stristr($uri, '/api.json/')
        ) {
            $model = $sm->get('ViewModel/Json');
            $type  = 'json';
        } elseif (stristr($accept, 'application/xml')
            || stristr($uri, '/api.xml/')
        ) {
            $model = $sm->get('ViewModel/Xml');
            $type  = 'xml';
        } elseif (stristr($uri, '/api/')) {
            //default to json if rest requested
            $model = $sm->get('ViewModel/Json');
            $type  = 'json';
        } else {
            //don't know what to do, just do a 404 and a blank page
            $eventManager->attach(
                MvcEvent::EVENT_FINISH,
                function ($event)  {
                    $event->getResponse()->setStatusCode(404);
                }
            );
            return;
        }
        ini_set('html_errors', 0);
        $model->setTerminal(true);
        $protocol    = $this->getProtocol();
        $description = $protocol . $_SERVER['HTTP_HOST']
            . "/errors/101010";
        $result      = array(
            'title' => 'Invalid URL',
            'httpStatus' => 404,
            'describedBy' => $description,
        );
        $model->setVariables($result);
        $model->setTerminal(true);
        $e->setResult($model);
        $e->setViewModel($model);

        $ua = $this->getUserAgent();
        if (!stristr($accept, 'text/html')) {
            //workaround to set content-type *after* it is set
            //by the view model
            $eventManager->attach(
                MvcEvent::EVENT_FINISH,
                function ($event) use ($type) {
                    $headers = $event->getResponse()
                        ->getHeaders();

                    $contentType = $headers
                        ->get('Content-Type');
                    while ($contentType !== false) {
                        if (is_array($contentType)) {
                            foreach ($contentType as $line) {
                                $headers->removeHeader($line);
                            }
                        } else {
                            $headers->removeHeader($contentType);
                        }
                        $contentType = $headers
                            ->get('Content-Type');
                    }
                    $headers->addHeaderLine(
                        "Content-Type: ".
                        "application/api-problem+$type"
                    );
                    $event->getResponse()->setStatusCode(404);
                }
            );
        } else {
            $eventManager->attach(
                MvcEvent::EVENT_FINISH,
                function ($event) use ($type) {
                    $event->getResponse()->setStatusCode(404);
                }
            );
        }

    }

    /**
     * If route maps to a RESTful controller, convert PHP Exception object
     * to REST/JSON object
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    protected function formatException(MvcEvent $e) {
        if ($e->getTarget() instanceof AbstractRestfulController) {
            //$error = $e->getError();
            $error = $e->getParam('exception');
            //invalid request JSON exceptions are not caught by our code
            if ($error instanceof JsonException) {
                $error = new InvalidJsonException();
            }
            $result = $e->getTarget()->displayError($error);
            $e->setResult($result);

            $em = $e->getApplication()->getEventManager();

            //ensure that 'content-type' header is set to api-problem
            $ua                = $this->getUserAgent();
            $changeContentType = true;
            if (stristr($ua, 'chrome')) {
                $changeContentType = false;
            }
            if ($changeContentType) {
                //display standard api-problem content-type
                $em->attach(MvcEvent::EVENT_RENDER, array($this, 'overrideContentType'), -10000);
            }
        } else {
            //default to zf2's exception strategy for display
            $sm                = $e->getApplication()->getServiceManager();
            $exceptionStrategy = $sm->get('ExceptionStrategy');
            $exceptionStrategy->prepareExceptionViewModel($e);
        }
    }

    /**
     * For caught exceptions, error code should be < 500. Unset the response's
     * 'exception' key, which shows up as an error in testing
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    public function unsetResultException(MvcEvent $e) {
        $error = $e->getParam('exception');
        if ($error) {
            $response = $e->getResponse();
            $code     = $response->getStatusCode();
            if ($code < 500) {
                $e->setError(false);
                $params = $e->getParams();
                unset($params['exception']);
                $e->setParams($params);
            }
        }
    }

    /**
     * Ensure correct content-type is displayed when showing errors in the
     * api-problem standard format
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function overrideContentType(MvcEvent $event) {
        $headers     = $event->getResponse()->getHeaders();
        $contentType = $headers->get('Content-Type');
        while ($contentType !== false) {
            if (is_array($contentType)) {
                foreach ($contentType as $headerLine) {
                    $value = $headerLine->getFieldValue();
                    $headers->removeHeader($headerLine);
                }
            } else {
                $value = $contentType->getFieldValue();
                $headers->removeHeader($contentType);
            }
            $contentType = $headers->get('Content-Type');
        }
        if (stristr($value, 'json')) {
            $type = "application/api-problem+json";
        } elseif (stristr($value, 'xml')) {
            $type = "application/api-problem+xml";
        } else {
            $type = $value;
        }
        $headers->addHeaderLine(
            "Content-Type: $type"
        );
        //low(late) priority, runs after ExceptionStrategy
        //sets content type
    }

    /**
     * Attach to dispatch.error event to run when uncaught errors are shown
     *
     * @param MvcEvent $e event
     *
     * @return void
     */
    protected function attachErrorLogger(MvcEvent $e) {
        //Log any Uncaught Exceptions, including all Exceptions in the stack
        $sm = $e->getApplication()->getServiceManager();
        if ($e->getParam('exception')) {
            $ex = $e->getParam('exception');
            //make sure it isn't logging invalid request json exception,
            //that is handled in catchRestExceptions, supposed to be
            //visible to user
            if (!($ex instanceof JsonException)) {
                $code = $ex->getCode();
                //only log errors the user shouldn't see (they should
                //be able to fix any 100000 level errors on their own)
                if ($code < 100000 || $code >= 200000) {
                    //defer until event_finish so we have access to response and
                    //transaction id
                    $eventManager = $e->getApplication()->getEventManager();
                    $eventManager->attach(MvcEvent::EVENT_FINISH, array($this, 'logError'), 1);
                }
            }
        }
    }

    /**
     * Write error to log
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    public function logError(MvcEvent $e) {
        $ex              = $e->getParam('exception');
        $requestHeaders  = $e->getRequest()->getHeaders()->toArray();
        $responseHeaders = $e->getResponse()->getHeaders()->toArray();
        $content         = $e->getRequest()->getContent();
        $sm              = $e->getApplication()->getServiceManager();
        do {
            $sm->get('Logger')->crit(
                sprintf(
                    "%s Code:%s\nFile:%s:line %d\nMessage:".
                    "%s\n%s\n\n_SERVER:\n%s\n\n\nRequest headers:\n%s\n\n".
                    "Response headers:\n%s\n\nData:\n%s",
                    get_class($ex),
                    $ex->getCode(),
                    $ex->getFile(),
                    $ex->getLine(),
                    $ex->getMessage(),
                    $ex->getTraceAsString(),
                    print_r($_SERVER, true),
                    print_r($requestHeaders, true),
                    print_r($responseHeaders, true),
                    $content
                )
            );
        } while ($ex = $ex->getPrevious());
    }
}
