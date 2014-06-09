<?php
namespace ShinyRest;

use \Zend\Mvc\MvcEvent;
use \Zend\ModuleManager\Feature\DependencyIndicatorInterface;
use \ShinyRest\Mvc\Controller\AbstractRestfulController;
use \ShinyRest\Mvc\RestViewModelAwareInterface;

class Module implements DependencyIndicatorInterface
{
	public function getModuleDependencies() 
	{
		return array ('ShinyLib');
	}

	public function onBootstrap(\Zend\Mvc\MvcEvent $e)
	{
		$this->catchRestExceptions($e);
		$this->catchRestRouteErrors($e);
	}
	
	/*
	 * Override dispatch error to display errors in requested REST format
	 */
	protected function catchRestExceptions(\Zend\Mvc\MvcEvent $e)
	{
		$em = $e->getApplication()->getEventManager();
		$sharedManager = $em->getSharedManager();
		$sm = $e->getApplication()->getServiceManager();
		//on dispatch error, do custom error display
		$sharedManager->attach('Zend\Mvc\Application', \Zend\Mvc\MvcEvent::EVENT_DISPATCH_ERROR,
			function($e) {
				return $this->onRestDispatchError($e);
			},
			100 //high priority, runs late, after ExceptionStrategy
		);
		//override content-type for displaying errors. Shiny AbstractRestfulController's getError() triggers
		$sharedManager->attach('*', 'overrideType', function ($e) use ($em)
		{
			$this->onContentTypeOverride($e, $em); #works on php>=5.4
		});
		//detach default route not found strategy, as it injects extra info when 404 returned
		//TODO see if non-rest (thrown) 404 still works, or only detach when 404 w/ rest
		$strategy = $sm->get('RouteNotFoundStrategy');
		//$strategy->detach($em);
	}

	/*
	 * Override Content-Type header with "api-problem+$type" 
	 * Attach to an event towards the end of the lifecycle and change content 
	 * type then
	 */
	//TODO move this to abstractRestfulController?
	protected function onContentTypeOverride($e, $em)
	{
		$params = $e->getParams();
		$type = $params['type'];
		//$sharedManager->attach('*', \Zend\Mvc\MvcEvent::EVENT_RENDER, function($event) use ($type)
		$em->attach(\Zend\Mvc\MvcEvent::EVENT_RENDER, function($event) use ($type)
			{   
				$event->getResponse()->getHeaders()->addHeaderLine('Content-Type: ' . "application/api-problem+$type");
			}, -10000 //low(early) priority, runs after(?) ExceptionStrategy sets content type
		);
	}

	protected function onRestDispatchError($e)
	{
		if ($e->getTarget() instanceof AbstractRestfulController)
		{
			//use AbstractRestfulController's exception display method
			$error = $e->getParam('exception');
			$e->setError(false);
			$result = $e->getTarget()->displayError($error);
			$e->setResult($result);
		}
		else
		{
			//maybe controller not found, but requested RESTfully. try to detect 
			//and display appropriately
			$eventManager = $e->getApplication()->getEventManager();
			return $this->displayRestError($e, $eventManager);
		}
		
	}

	/*
	 * Check for error during render event'
	 */
	protected function catchRestRouteErrors(MvcEvent $e)
	{
		$eventManager = $e->getApplication()->getEventManager();
		//When render event occurs, check if there was an error
		$eventManager->attach(MvcEvent::EVENT_RENDER, function($e) use ($eventManager)
			{
				//if it wasn't an error, we don't care
				if (!$e->isError())
				{
					return;
				}
				//using $this in lambda works on php>=5.4
				return $this->displayRestError($e, $eventManager); 
			}
		);
	}

	/*
	 * Ensure that REST route errors are handled by responding with the 
	 * appropriate Content-Type
	 * TODO outsource this to a class
	 */
	protected function displayRestError($e, $eventManager) 
	{
		$sm = $e->getApplication()->getServiceManager();
		$result = $e->getResult();
		$reason = (is_object($result))?$result->reason:"";
		$error = $e->getError();

		//check if it was a routing error
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")?"https://":"http://";
		if ($reason == "error-router-no-match" || $error == "error-router-no-match" || $error == "error-controller-not-found")
		{
			$viewVariables = array(
				'title'=>'Invalid URL',
				'httpStatus'=>404,
				//TODO change error url to fit with something that 
				//works well as a module
				'describedBy'=>$protocol . $_SERVER['HTTP_HOST'] . "/errors/101010",
			);
		}
		else
		{
			//TODO log this and find out if there is another error case to 
			//handle
			$viewVariables = array(
				'title'=>'Unknown Error',
				'httpStatus'=>500,
				//TODO change error url to fit with something that 
				//works well as a module
				'describedBy'=>$protocol . $_SERVER['HTTP_HOST'] . 
					"/errors/101010",
			);
		}
		//now we determine what kind of content to return
		$request = $e->getRequest();
		$accept = $request->getHeaders()->get('Accept');
		if (!is_object($accept)) //no headers found... unit testing?
		{
			return;
		}
		$accept = $accept->toString();
		$uri = $request->getRequestUri();
		if (
			stristr($accept, 'text/html')
			|| stristr($accept, 'application/html+xml')
			|| stristr($accept, '*/*')
		)
		{
			//likely a browser, don't do anything
			return;
		}
		elseif (stristr($accept, 'application/json') 
			#|| stristr($uri, '/rest.json/')  #TODO allow user to define 
		)
		{
			$model = $sm->get('ViewModel/Json');
			$type='json';
		}
		elseif (stristr($accept, 'application/xml')
			|| stristr($uri, '/rest.xml/') #TODO allow user to define 
		)
		{
			$model = $sm->get('ViewModel/Xml');
			$type='xml';
		}
		//default to json if rest url requested
		#elseif (stristr($uri, '.com/rest/') ) //TODO let user define
		#{
		#	$model = $sm->get('ViewModel/Xml');
		#	$type='xml';
		#}
		else //FIXME? don't know what else to do
		{
			return;
		}
		if (isset($model)) //probably want rest error
		{
			ini_set('html_errors', 0); 
			$model->setTerminal(true);
			$model->setVariables($viewVariables);
			$model->setTerminal(true);
			$e->setResult($model);
			$e->setViewModel($model);
			//workaround to set content-type *after* it is set by the view model
			$eventManager->attach(\Zend\Mvc\MvcEvent::EVENT_FINISH, function($event) use ($type)
			{   
				$event->getResponse()->getHeaders()->addHeaderLine('Content-Type: ' . "application/api-problem+$type");
				$event->getResponse()->setStatusCode(404);
				//$event->getResponse()->getHeaders()->addHeaderLine('Content-Type: ' . "text/plain");
			});

		}
	}

	
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

	public function getServiceConfig()
	{
		return array(
			'invokables'=>array(
				'XmlModel'=>'\ShinyRest\View\Model\XmlModel',
				'JsonModel'=>'\Zend\View\Model\JsonModel',
				'ViewXmlRenderer'=>'\ShinyRest\View\Renderer\XmlRenderer',
			),
			'aliases'=>array
			(
				'ViewModel\Xml'=>'XmlModel',
				'ViewModel\Json'=>'JsonModel',
				'Renderer\Xml'=>'ViewXmlRenderer',
			),
			//'initializers'=>array
			//(
			//	'restViewModel' => function ($service, $sm) {
			//		if ($service instanceof RestViewModelAwareInterface) 
			//		{
			//			$xmlModel = $sm->get("ViewModel\Xml");
			//			$jsonModel = $sm->get("ViewModel\Json");
			//			$service->setXmlModel($xmlModel);
			//			$service->setJsonModel($jsonModel);
			//		}
			//	},
			//),
			'factories'=>array(
				'ViewXmlStrategy'=>function ($sm)
				{
					$renderer = $sm->get("Renderer\Xml");
					$strategy = new View\Strategy\XmlStrategy($renderer);
					return $strategy;
				},
			)
		);
	}

	public function getControllerConfig()
	{
		return array(
			'initializers'=>array
			(
				'restViewModel' => function ($service, $sm) {
					if ($service instanceof RestViewModelAwareInterface) 
					{
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
