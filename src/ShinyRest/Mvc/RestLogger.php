<?php

namespace ShinyRest\Mvc;

use ShinyRest\Mvc\Controller\AbstractRestfulController;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

class RestLogger extends AbstractListenerAggregate
{

    /**
     * Log requests and responses
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onBootstrap(MvcEvent $event) {
        $this->serviceManager = $event->getApplication()->getServiceManager();
        $this->event          = $event;
    }

    /**
     * Log requests and responses
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onDispatch(MvcEvent $event) {
        $this->event              = $event;
        $this->mvcEventManager    = $event->getApplication()->getEventManager();
        $this->sharedEventManager = $this->mvcEventManager->getSharedManager();
        $this->serviceManager     = $event->getApplication()->getServiceManager();
        $this->logRequest();
    }

    /**
     * Log download requests and responses
     *
     * @param object $e
     *
     * @return void
     */
    public function onDownload($e) {
        $e;
        $this->logRequest();
    }

    /**
     * Attach events
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function attach(EventManagerInterface $events) {
        //high priority here allows attaching to save servicemanager as ivar for download event
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH,
            array($this,
            'onBootstrap'),
            10000
        );
        //default priority here allows event target to be controller
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH,
            array($this,
            'onDispatch')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH_ERROR,
            array($this,
            'onDispatch')
        );
        $sharedManager     = $events->getSharedManager();
        $this->listeners[] = $sharedManager->attach(
            '*',
            'download',
            array($this,
            'onDownload')
        );
    }

    /**
     * Write to log, attach to finish event to write response
     *
     * @return void
     */
    protected function logRequest() {
        $sm                  = $this->serviceManager;
        $e                   = $this->event;
        if ($e->getTarget() instanceof AbstractRestfulController) {
            $controller = $e->getTarget();
            $routeMatch = $controller->getEvent()
                ->getApplication()->getMvcEvent()->getRouteMatch();

            $identifier        = $routeMatch->getParam('controller');
            $controllerManager = $sm->get('ControllerLoader');
            $controller        = $controllerManager->get($identifier);
            $controllerClass   = get_class($controller);
            $pos               = strpos($controllerClass, '\\');
            $match             = substr($controllerClass, 0, $pos);
            $module            = strtoupper($match);
            $serviceLocator    = $controller->getServiceLocator();
            $request           = $controller->getRequest();
            $headers           = $request->getHeaders()->toArray();
            $method            = $request->getMethod();
            $params            = array();
            $query             = $request->getQuery()->toArray();
            $post              = $request->getPost()->toArray();
            $file              = $request->getFiles()->toArray();
            $env               = $request->getEnv()->toArray();
            if (!empty($query)) {
                $params['query'] = $query;
            }
            if (!empty($post)) {
                $params['post'] = $post;
            }
            if (!empty($file)) {
                $params['file'] = $file;
            }
            if (!empty($env)) {
                $params['env'] = $env;
            }
            $uri = $request->getRequestUri();
            if (stristr($uri, 'ecgs/drop') !== false) {
                $content = $request->getPost('file');
                $content = str_replace('data:;base64,', '', $content);
                $content = str_replace(' ', '+', $content);
                $content = base64_decode($content);
            } else {
                $content = $request->getContent();
            }
            $data = array();
            $data['request'] = compact(
                'params',
                'uri',
                'method',
                'content',
                'headers'
            );
            $logger          = $serviceLocator->get('RestLog');
            $logString       = "Request: \n";
            $logString      .= print_r($data, true);
            $logString       = preg_replace(
                '/([\r\n])/',
                "\r\n ",
                $logString
            );
            $logger->info($logString);
            $eventManager = $controller->getEvent()
                ->getApplication()->getEventManager();
            $eventManager->attach(
                MvcEvent::EVENT_FINISH,
                function ($event) use (
                    $logger
                ) {
                    $response      = $event->getResponse();
                    $headerObject  = $response->getHeaders();
                    $headers          = $headerObject->toArray();
                    $content          = $response->getContent();
                    $statusCode       = $response->getStatusCode();
                    $data             = array();
                    $data['response'] = compact('headers', 'content');
                    $logString  = "Response:\n";
                    $logString .= print_r($data, true);
                    $logString  = preg_replace(
                        '/([\r\n])/',
                        "\r\n ",
                        $logString
                    );
                    $logString  = preg_replace(
                        '/.n(#\d)/',
                        "\r\n \${1}",
                        $logString
                    );
                    if ($statusCode < 300
                        || APPLICATION_ENV === "testing"
                    ) {
                        $logger->info($logString);
                    } elseif ($statusCode < 500) {
                        $logger->warn($logString);
                    } else {
                        $logger->crit($logString);
                    }
                },
                100
            );
        }
    }
}
