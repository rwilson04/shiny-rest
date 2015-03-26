<?php
namespace ShinyRest;

use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\DependencyIndicatorInterface;
use Zend\Mvc\ModuleRouteListener;
use ShinyRest\Mvc\Controller\AbstractRestfulController;
use ShinyRest\Mvc\RestViewModelAwareInterface;

class Module implements DependencyIndicatorInterface
{
    public function getModuleDependencies() {
        return array ('ShinyLib');
    }

    public function onBootstrap(\Zend\Mvc\MvcEvent $e) {
        //use zf2 modules
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        //custom exception handling
        $this->attachRestExceptionStrategy($e);
        $this->correct204Response($e);

        //set up request logger
        $this->logRestRequests($e);
    }

    /**
     * Handle exceptions restfully
     *
     * @param MvcEvent $e
     *
     * @return void
     */
    protected function attachRestExceptionStrategy(MvcEvent $e) {
        $em                    = $e->getApplication()->getEventManager();
        $sm                    = $e->getApplication()->getServiceManager();
        $restExceptionStrategy = $sm->get('ExceptionStrategy\Rest');
        $em->attach($restExceptionStrategy);
        //unset default exception strategies
        $exceptionStrategy = $sm->get('ExceptionStrategy');
        $exceptionStrategy->detach($em);
        $strategy = $sm->get('RouteNotFoundStrategy');
        $strategy->detach($em);
    }

    /**
     * Make sure 204 No Content response actually has no content
     *
     * @param MvcEvent $e event
     *
     * @return void
     */
    protected function correct204Response(MvcEvent $e) {
        $eventManager = $e->getApplication()->getEventManager();
        $sm           = $e->getApplication()->getServiceManager();
        $eventManager->attach(
            MvcEvent::EVENT_FINISH,
            function ($event) use ($sm, $eventManager) {
                $code = $event->getResponse()->getStatusCode();
                if ($code === 204) {
                    $event->getResponse()->setContent("");
                }
            }
        );
    }

    /**
     * log all rest requests
     *
     * @param MvcEvent $e event
     *
     * @return void
     */
    protected function logRestRequests(MvcEvent $e) {
        $em     = $e->getApplication()->getEventManager();
        $sm     = $e->getApplication()->getServiceManager();
        $logger = $sm->get('RestRequestLogger');
        $em->attach($logger);
    }

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getServiceConfig() {
        return array(
            'invokables'=>array(
                'XmlModel'=>'\ShinyRest\View\Model\XmlModel',
                'JsonModel'=>'\Zend\View\Model\JsonModel',
                'ViewXmlRenderer'=>'\ShinyRest\View\Renderer\XmlRenderer',
            ),
            'aliases'=>array
            (
                'ExceptionStrategy\Rest' => 'RestExceptionStrategy',
                'ViewModel\Xml'=>'XmlModel',
                'ViewModel\Json'=>'JsonModel',
                'Renderer\Xml'=>'ViewXmlRenderer',
            ),
            //'initializers'=>array
            //(
            //  'restViewModel' => function ($service, $sm) {
            //      if ($service instanceof RestViewModelAwareInterface)
            //      {
            //          $xmlModel = $sm->get("ViewModel\Xml");
            //          $jsonModel = $sm->get("ViewModel\Json");
            //          $service->setXmlModel($xmlModel);
            //          $service->setJsonModel($jsonModel);
            //      }
            //  },
            //),
            'factories'=>array(
                'RestErrorLogger' => function ($sm) {
                    $logger = new \Zend\Log\Logger();
                    $nullWriter = new \Zend\Log\Writer\Null();
                    $logger->addWriter($nullWriter);
                    return $logger;
                },
                'RestLog' => function ($sm) {
                    $logger = new \Zend\Log\Logger();
                    $nullWriter = new \Zend\Log\Writer\Null();
                    $logger->addWriter($nullWriter);
                    return $logger;
                },
                'RestRequestLogger' => function($sm) {
                    $logger = new \ShinyRest\Mvc\RestLogger();
                    return $logger;
                },
                'RestExceptionStrategy' => function($sm) {
                    $strategy = new \ShinyRest\Mvc\RestExceptionStrategy();
                    return $strategy;
                },
                'ViewXmlStrategy'=>function ($sm) {
                    $renderer = $sm->get("Renderer\Xml");
                    $strategy = new View\Strategy\XmlStrategy($renderer);
                    return $strategy;
                },
            )
        );
    }

    public function getControllerConfig() {
        return array(
            'initializers'=>array
            (
                'restViewModel' => function ($service, $sm) {
                    if ($service instanceof RestViewModelAwareInterface) {
                        $locator = $sm->getServiceLocator();
                        $xmlModel = $locator->get("ViewModel\Xml");
                        $jsonModel = $locator->get("ViewModel\Json");
                        $service->setXmlModel($xmlModel);
                        $service->setJsonModel($jsonModel);
                    }
                },
            ),
        );
    }
}
