<?php
/**
 * This class adapted from Zend\View\Strategy\JsonStrategy
 *
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_View
 */

namespace ShinyRest\View\Strategy;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Http\Request as HttpRequest;
use ShinyRest\View\Model;
use ShinyRest\View\Renderer\XmlRenderer;
use Zend\View\ViewEvent;

/**
 * @category   Zend
 * @package    Zend_View
 * @subpackage Strategy
 */
class XmlStrategy implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * @var XmlRenderer
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param  XmlRenderer $renderer
     */
    public function __construct(XmlRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Attach the aggregate to the specified event manager
     *
     * @param  EventManagerInterface $events
     * @param  int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RENDERER, array($this, 'selectRenderer'), $priority);
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RESPONSE, array($this, 'injectResponse'), $priority);
    }

    /**
     * Detach aggregate listeners from the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Detect if we should use the XmlRenderer based on model type and/or
     * Accept header
     *
     * @param  ViewEvent $e
     * @return null|XmlRenderer
     */
    public function selectRenderer(ViewEvent $e)
    {
        $model = $e->getModel();

        $request = $e->getRequest();
        if (!$request instanceof HttpRequest) {
            // Not an HTTP request; cannot autodetermine
            return ($model instanceof Model\XmlModel) ? $this->renderer : null;
        }

        $headers = $request->getHeaders();
        if (!$headers->has('accept')) {
            return ($model instanceof Model\XmlModel) ? $this->renderer : null;
        }

        $accept  = $headers->get('Accept');
        if (($match = $accept->match('application/xml')) == false) {
            return ($model instanceof Model\XmlModel) ? $this->renderer : null;
        }

        #if ($match->getTypeString() == 'application/xml') {
        #    // application/xml Accept header found
        #    return $this->renderer;
        #}


        return ($model instanceof Model\XmlModel) ? $this->renderer : null;
    }

    /**
     * Inject the response with the Xml payload and appropriate Content-Type header
     *
     * @param  ViewEvent $e
     * @return void
     */
    public function injectResponse(ViewEvent $e)
    {
        $renderer = $e->getRenderer();
        if ($renderer !== $this->renderer) {
            // Discovered renderer is not ours; do nothing
            return;
        }

        $result   = $e->getResult();
        if (!is_string($result)) {
            // We don't have a string, and thus, no Xml
            return;
        }

        // Populate response
        $response = $e->getResponse();
        $response->setContent($result);
        $headers = $response->getHeaders();
		$headers->addHeaderLine('content-type', 'application/xml');
    }
}
