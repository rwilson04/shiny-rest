<?php
namespace ShinyRest\Mvc\Controller;

use Zend\Mvc\MvcEvent;
use Zend\Stdlib\RequestInterface as Request;
use Zend\Http\PhpEnvironment\Response;
use Zend\View\Model\ViewModel;
use ShinyRest\Mvc\Exception\RuntimeException;
use ShinyRest\Mvc\Exception\BadRestMethodException;
use ShinyRest\Mvc\Exception\DomainException;
use ShinyRest\Exception\ExceptionInterface;
use ShinyRest\Mvc\RestViewModelAwareInterface;

/*
 * Handles JSON and XML rest formats, and facilitates displaying errors in the api-problem standard
 */
abstract class AbstractRestfulController extends \Zend\Mvc\Controller\AbstractRestfulController implements RestViewModelAwareInterface
{
    /**
     * @var object application configuration service
     */
    protected $config;

    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var ServiceManager
     */
    protected $services;

    protected $xmlModel;
    protected $jsonModel;
    protected $jsonFormatters = array('json');
    protected $xmlFormatters = array('xml');
    protected $jsonAcceptTypes = array('application/json');
    protected $xmlAcceptTypes = array('application/xml');
    #protected $browserAcceptTypes = array('application/html+xml', 'text/html', '*/*');
    protected $browserAcceptTypes = array('application/html+xml', 'text/html');

    protected $headers;
    /*
     * Get request headers as an array
     */
    protected function getHeaders() {
        $headersObject = $this->getRequest()->getHeaders();
        $headers = $headersObject->toArray();
        return $headers;
    }


    /*
     * Runs for OPTIONS requests
     * Show allowed HTTP methods, as well as any other requirements for
     * interacting with resources
     */
    public function options() {
        $response = $this->getResponse();
        $headers  = $response->getHeaders();

        // If you want to vary based on whether this is a collection or an
        // individual item in that collection, check if an identifier from
        // the route is present
        if ($this->params()->fromRoute('id', false)) {
            // Allow viewing, partial updating, replacement, and deletion
            // on individual items
            $headers->addHeaderLine('Allow', implode(',', array(
                'GET',
                'PATCH',
                'PUT',
                'DELETE',
            )));
        }

        // Allow only retrieval and creation on collections
        $headers->addHeaderLine('Allow', implode(',', array(
            'GET',
            'POST',
        )));
        //TODO return requirements for interacting with resources
        //see http://zacstewart.com/2012/04/14/http-options-method.html
        $apiRequirements = array("TODO"=>"API requirements go here");
        return $this->display($apiRequirements);
    }
    ///**
    // * HTTP OPTIONS request
    // *
    // * @return mixed
    // */
    //public function options() {
    //    $method = $this->params('method');
    //    $result = $this->getBadMethodError($method);
    //    $return = $this->display($result);
    //    return $return;
    //}

   /**
     * HTTP POST without an id param
     *
     * @param array $data Data
     *
     * @return mixed
     */
    public function create($data) {
        $data;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP GET with no id param
     *
     * @return mixed
     */
    public function getList() {
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP HEAD request
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function head($id = null) {
        $id;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP PATCH with an id param
     *
     * @param mixed $id
     * @param array $data
     *
     * @return mixed
     */
    public function patch($id, $data) {
        $id;
        $data;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP PUT with an id param
     *
     * @param mixed $id
     * @param array $data
     *
     * @return mixed
     */
    public function update($id, $data) {
        $id;
        $data;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP PUT with no id param
     *
     * @param array $data
     *
     * @return mixed
     */
    public function replaceList($data) {
        $data;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP PATCH with no id param
     *
     * @param array $data
     *
     * @return mixed
     */
    public function patchList($data) {
        $data;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP GET with id param
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function get($id) {
        $id;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP DELETE with id param
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function delete($id) {
        $id;
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }

    /**
     * HTTP DELETE with no id param
     *
     * @return mixed
     */
    public function deleteList() {
        $method = $this->params('method');
        $result = $this->getBadMethodError($method);
        $return = $this->display($result);
        return $return;
    }


    /*
     * Override request type if specified in header
     *
     * @param MvcEvent $e
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        $request = $e->getRequest();
        $headers = $request->getHeaders();
        $method  = $headers->get('X-HTTP-Method-Override');
        if ($method) {
            $request->setMethod($method->getFieldValue());
        }
        $response = parent::onDispatch($e);
        // Override ZF2 behavior which doesn't return content for OPTIONS method
        $request = $e->getRequest();
        $method = strtolower($request->getMethod());
        if ($method === "options") {
            $e->setResult($this->options());
            return $e;
        }

        //get config
        $application  = $e->getApplication();
        $services     = $application->getServiceManager();
        $config       = $services->get('Config');
        $this->services = $services;
        if (
            isset($config['view_manager'])
            && (is_array($config['view_manager'])
            || $config['view_manager'] instanceof ArrayAccess)
        ) {
            $this->config   = $config['view_manager'];
        } else {
            $this->config   = array();
        }
        return $response;
    }

    //injected with initializer
    public function setXmlModel(ViewModel $model) {
        $this->xmlModel = $model;
    }

    //injected with initializer
    public function setJsonModel(ViewModel $model) {
        $this->jsonModel = $model;
    }

    public function getXmlModel() {
        return $this->xmlModel;
    }

    public function getJsonModel() {
        return $this->jsonModel;
    }

    /*
     * For clients that can't generate certain types of HTTP requests, allow
     * them to set a header value instead
     *
     * @param mixed  $data   POST data
     *
     * @return bool|ViewModel   False if not overriding, else desired
     *                        method's results if it found a valid
     *                        override header
     * @throws DomainException
     */
    protected function methodOverride($data) {
        $override = $this->params()->fromHeader('X-Http-Method-Override');
        if ($override !== null) {
            $method = $override->getFieldValue();
            $id = $this->params('id');
            switch ($method) {
                case "PATCH":
                    return $this->patch($id, $data);
                    break;
                case "PUT":
                    return $this->update($id, $data);
                    break;
                case "DELETE":
                    return $this->delete($id);
                    break;
                default :
                    throw new DomainException("Unknown HTTP Method $method. Override failed");
            }
        }
        return false;

    }

    /*
     * Force download
     *
     * @param $contents  binary  file contents
     * @param $filename  string  name of the download
     */
    public function displayDownload($contents, $fileName) {
        $response = new Response();
        $headers  = $response->getHeaders();
        $headers->addHeaderLine('Content-Disposition: attachment; filename="'.$fileName.'"');
        $headers->addHeaderLine('Content-Type: ' . "application/force-download");
        $headers->addHeaderLine('Content-Length: ' . strlen($contents));
        $response->setContent($contents);
        return $response;
    }

    /*
     * Render view variables in RESTful format.
     * Get format from url 'formatter' param or 'Accept' header. JSON takes precedence.
     *
     * @param result    array   key=>value view variables
     *
     * @return ViewModel
     */
    public function display($result) {
        $type = $this->getRestAcceptType();
        if ($type === 'json') {
            $model = $this->getJsonModel();
        } elseif ($type === 'xml') {
            $model = $this->getXmlModel();
        } elseif ($type === 'browser') {
            //check if formatter param set to json. if not, default to xml
            if ($this->acceptsJson()) {
                $model = $this->getJsonModel();
            } else {
                $model = $this->getXmlModel();
            }
        } else {
            return $this->notAcceptable($result);
        }
        $model->setVariables($result, true);
        $model->setTerminal(true);
        return $model;
    }

    /*
     * Some 'Accept' type that we don't provide was requested. Respond with
     * an error in XML format and content-type
     *
     * @param array  as a backup in case user needs to see results, at
     *                least they aren't gone in a black hole
     */
    protected function notAcceptable($result) {
            $message = "Not acceptable";
            $details = array(
                "detail"=>"Unknown 'Accept' header and/or REST formatter",
                "resolution"=>"Specify formatter in URL or 'Accept' header ".
                    "to get results in desired format type.",
                "allowedFormatters" => array('allowedFormatter'=>array_merge($this->jsonFormatters, $this->xmlFormatters)),
                "allowedAcceptTypes" => array('allowedAcceptType'=>array_merge($this->jsonAcceptTypes, $this->xmlAcceptTypes)),
                "originalResults"=>$result,
            );
            //TODO change error code to fit with something that works well in a
            //module context, maybe with traits and constants
            $code = 101000;
            $previous = null;
            $httpStatus = 406;
            $response = $this->getResponse();
            $response->setStatusCode($httpStatus);
            //http_response_code($httpStatus);
            $result = $this->getError($message, $details, $httpStatus, $code);
            //$type = 'xml';
            //$this->getEventManager()->trigger('overrideType', $this, compact('type'));
            //throw new RuntimeException($message, $code, $previous,
                //$httpStatus, $details);
            $model = $this->getXmlModel();
            $model->setVariables($result, true);
            $model->setTerminal(true);
            return $model;
    }

    /*
     * Get accept type from headers or formatter in url. set to default if not specified
     *
     * @return string|boolean   String type if detected, false if unable to provide acceptable type
     */
    protected function getRestAcceptType() {
        $formatter = $this->params('formatter');
        if ($this->acceptsBrowserTypes()) {
            return 'browser';
        } elseif ($this->acceptsJson()) {
            return 'json';
        } elseif ($this->acceptsXml()) {
            return 'xml';
        } elseif ($this->acceptsAny()) {
            return 'json';
        } else //wants something, but not what we provide
        {
            return false;
        }
    }

    /*
     * Custom rendering of exceptions
     *
     * Overrides final display when error caught during dispatch
     * Event handler defined in Module.php
     * Triggers error logging
     * Determines whether user should see the error based on the error code.
     *
     * @param   $exception Exception    Exception to display
     */
    public function displayError(\Exception $exception) {
        $originalException = $exception;
        $code = $exception->getCode();
        //if extended exception was thrown, we have additional details
        if ($exception instanceof ExceptionInterface) {
            $httpStatus = $exception->getHttpStatus();
            $details = $exception->getDetails();
        } else {
            $httpStatus = 500;
            $details = array();
        }
        $displayExceptions     = false;
        if (isset($this->config['display_exceptions'])) {
            $displayExceptions = $this->config['display_exceptions'];
        }
        $message = $exception->getMessage();
        //determine whether this is an error the user should see
        if (($code<100000 || $code>=200000) && !$displayExceptions) {
            //exception code is in the range where the message should be
            //displayed to the user
            $message = "An unexpected error has occurred";
            $details['detail'] = "This might be the developer's fault. The developer has been notified of this occurrence";
        }
        //show exception details if config set
        if ($displayExceptions) {
            $exceptions = array();
            do {
                $exceptions['exception'][] = array(
                    'message'=>$exception->getMessage(),
                    'code'=>$exception->getCode(),
                    'file'=>$exception->getFile(),
                    'line'=>$exception->getLine(),
                    'trace'=>$exception->getTraceAsString()
                );
            } while ($exception = $exception->getPrevious());
            $details['exceptions'] = $exceptions;
        }
        //TODO attach to this event, email if not in user visible range
        $this->getEventManager()->trigger('displayError', $this,
            $originalException);
        $result = $this->getError($message, $details, $httpStatus, $code);
        $return = $this->display($result);
        return $return;
    }

    /*
     * Check what format the REST input data is in, convert it to a PHP array
     *
     * When additional data is posted with the REST request, it needs to be parsed
     */
    public function dataToArray($inputData) {
        if ($this->isJson() | $this->isXml()) {
            $method = $this->getMethod();
            //workaround for zf2 post not working well with data files
            //handle PUT and POST differently. putprocessing overridden already to return data stream properly
            if ($method === "POST") {
                $stream = file_get_contents("php://input");
            } else //PUT
            {
                $stream = $inputData;
            }
            if (empty($stream)) {
                //$data = $this->getError("No data submitted", null, 400, 101005);
                $message = "No data submitted";
                $details = array(
                    "detail"=>"Required data field(s) empty or not detected",
                    "resolution"=>"Ensure that all required data is sent ".
                        "with request",
                );
                $code = 101005;
                $previous = null;
                //$httpStatus = 400;
                $httpStatus = 422;
                throw new RuntimeException($message, $code, $previous,
                    $httpStatus, $details);
                //$data = $this->getError("No data submitted", null, 400, 101005);
                //return null;
            } else {
                if ($this->isJson()) {
                    $asArray = true;
                    $data = json_decode($stream, $asArray);
                    if ($data === null) {
                        $message = "Invalid JSON";
                        $details = array(
                            "detail"=>"JSON could not be parsed",
                            "resolution"=>"Ensure that JSON data is well ".
                                "formed",
                        );
                        $code = 101002;
                        $previous = null;
                        $httpStatus = 400;
                        throw new RuntimeException($message, $code, $previous,
                            $httpStatus, $details);
                        //$data = $this->getError("Invalid JSON", null, 400, 101002);
                    }
                } else {
                    try
                    {
                        $root = (empty($stream))?array('root'=>null):$this->createArray($stream);
                        $data = $root['root'];
                    }

                    catch (\Exception $e) {
                        //TODO catch only correct exception type for xml parse error in createArray function
                        $message = "Invalid XML";
                        $details = array(
                            "detail"=>"XML could not be parsed",
                            "resolution"=>"Ensure that XML data is well ".
                                "formed",
                        );
                        $code = 101003;
                        $previous = null;
                        $httpStatus = 400;
                        throw new RuntimeException($message, $code, $previous,
                            $httpStatus, $details);
                        //$data = $this->getError("Invalid XML", null, 400, 101003);
                    }
                }
            }
        } elseif ($this->isForm()) {
            //TODO validate data
            //get data from url if it exists
            $data = array_replace_recursive($inputData,
                $this->params()->fromQuery());
            //$data = $inputData;
        } else {
            $headers = $this->getHeaders();
            if (array_key_exists('Content-Type', $headers)) {
                $detail="Content-Type header \"" . $headers['Content-Type'] .
                    "\" not allowed";
            } else {
                $detail="No Content-Type header detected";
            }
            $message = "Unsupported type";
            $details = array(
                "detail"=>$detail,
                "resolution"=>"Change Content-Type header to a known type ".
                    "before resubmitting request. Known types include ".
                    "application/json, application/xml, and ".
                    "application/x-www-form-urlencoded",
            );
            $code = 101003;
            $previous = null;
            $httpStatus = 400;
            throw new RuntimeException($message, $code, $previous,
                $httpStatus, $details);
            //$data = $this->getError("Invalid Content-Type", $details, 415, 101004);
        }
        return $data;
    }

    /*
     * Check request data type
     */
    public function isForm() {
        $headers = $this->getHeaders();
        if (array_key_exists('Content-Type', $headers)) {
            $contentType = $headers['Content-Type'];
            if ($contentType === "application/x-www-form-urlencoded") {
                return true;
            }
        }
        return false;
    }

    /*
     * Check request data type
     */
    public function isJson() {
        $headers = $this->getHeaders();
        if (array_key_exists('Content-Type', $headers)) {
            $contentType = $headers['Content-Type'];
            if ($contentType === "application/json") {
                return true;
            }
        }
        return false;
    }

    /*
     * Check request data type
     */
    public function isXml() {
        $headers = $this->getHeaders();
        if (array_key_exists('Content-Type', $headers)) {
            $contentType = $headers['Content-Type'];
            if ($contentType === "application/xml") {
                return true;
            }
        }
        return false;
    }

    /*
     * Check request Accept type
     */
    public function acceptsBrowserTypes() {
        $headers = $this->getHeaders();
        if (isset($headers['Accept']) && count($this->browserAcceptTypes) > 0) {
            foreach ($this->browserAcceptTypes as $type) {
                $accept = $headers['Accept'];
                if (stristr($accept, $type)) {
                    return true;
                }
            }

        }
        return false;
    }

    /*
     * Check request Accept type and url formatter param
     */
    public function acceptsAny() {
        $headers = $this->getHeaders();
        if (isset($headers['Accept'])) {
            $accept = $headers['Accept'];
            if (stristr($accept, "*/*")) {
                return true;
            }
        }
        return false;
    }

    /*
     * Check request Accept type and url formatter param
     */
    public function acceptsJson() {
        $headers = $this->getHeaders();
        if (isset($headers['Accept']) && count($this->jsonAcceptTypes) > 0) {
            foreach ($this->jsonAcceptTypes as $type) {
                $accept = $headers['Accept'];
                if (stristr($accept, $type)) {
                    return true;
                }
            }
        }
        $formatter = $this->params('formatter');
        if (count($this->jsonFormatters)>0) {
            foreach ($this->jsonFormatters as $jsonFormatter) {
                if ($formatter === $jsonFormatter) {
                    return true;
                }
            }
        }
        return false;
    }

    /*
     * Check request Accept type and url formatter param
     */
    public function acceptsXml() {
        $headers = $this->getHeaders();
        if (isset($headers['Accept']) && count($this->xmlAcceptTypes) > 0) {
            foreach ($this->xmlAcceptTypes as $type) {
                $accept = $headers['Accept'];
                if (stristr($accept, $type)) {
                    return true;
                }
            }
        }
        $formatter = $this->params('formatter');
        if (count($this->xmlFormatters)>0) {
            foreach ($this->xmlFormatters as $xmlFormatter) {
                if ($formatter === $xmlFormatter) {
                    return true;
                }
            }
        }
        return false;
    }

    /*
     * Return a standardized success array for REST success messages
     */
    public function getSuccess($message, $data = array(), $statusCode=200) {
        $code = $this->getResponse()->getStatusCode();
        //if ($code === 200) //TODO find out if this is needed
        //{
            $this->getResponse()->setStatusCode($statusCode);
        //}
        $success = array('success'=>true);
        if (!empty($message)) {
            $success['message'] = $message;
        }
        if (!empty($data)) {
            $success['data'] = $data;
        }
        return $success;
    }

    /*
     * Request type (GET/POST) doesn't exist for specified resource
     *
     * @param string    $method  method or resource type (usually
     *                            specified by url)
     * @throws BadRestMethodException
     */
    protected function getBadMethodError($method) {
        $message = "Method not allowed";
        $httpMethod = $this->getRequest()->getMethod();
        $details = array(
            'detail'=>"Invalid combination of REST resource/method/type parameter ".
                "'$method' and request's HTTP method '$httpMethod'",
        );
        $detail = "Invalid combination of REST method/type ".
                "\"$method\" and request's HTTP method \"$httpMethod\"";
        $details = compact('detail');
        throw new BadRestMethodException($message, null, null, null, $details);
        //$result = $this->getError($message, $details, 405, 101006);
        //return $result;
    }

    /*
     * Return a standardized error array for REST api-problem messages
     * Respond with content-type "api-problem+$format", unless browser accept
     * type  detected, then display normal xml (or json, if formatter
     * still present)
     *
     * @param string        $message    Error message
     * @param string|array  $details    String listed under details tag, array
     *                                requires 'detail' key to exist. other
     *                                entries are merged to the root
     */
    protected function getError($message, $details = array(), $statusCode=500, $errorCode=0) {
        $response = $this->getResponse();
        $type = $this->getRestAcceptType();
        if ($type !== 'browser') {
            $this->getEventManager()->trigger('overrideType', $this, compact('type'));
        }
        $error = array("title"=>$message);
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")?"https://":"http://";
        //$error['describedBy'] = $protocol . $_SERVER['HTTP_HOST'] . "/errors/descriptions.html";
        if (substr($errorCode, 0, 1) == "2") {
            $error['describedBy'] = $protocol . $_SERVER['HTTP_HOST'] . "/errors/serverError";
        } else {
            $error['describedBy'] = $protocol . $_SERVER['HTTP_HOST'] . "/errors/" .$errorCode;
        }
        $error['httpStatus'] = $statusCode;
        $response->setStatusCode($statusCode);
        if (!empty($details)) {
            if (is_string($details)) {
                $error['detail'] = $details;
            } else {
                //$error = array_merge($details, $error); //puts in wrong order (i.e. details before error)
                //FIXME deal with array key conflicts
                $error = array_merge($error, $details);
            }
        }
        return $error;
    }

    /*
     * Detect whether a view variables array is in the api-problem format
     *
     * @param array  $result     Array of view variables
     */
    public function isError($result) {
        return (isset($result['httpStatus']));
    }

    /*
     * override PUT processing to allow binary files (ZF2 bug? for which version?)
     */
    public function processPutData(Request $request, $routeMatch) {
        if (null === $id = $routeMatch->getParam('id')) {
            if (!($id = $request->getQuery()->get('id', false))) {
                throw new DomainException('Missing identifier');
            }
        }
        $content = $request->getContent();
        if ($this->isForm()) //only try to parse php://input as php params if form type PUT
        {
            parse_str($content, $parsedParams);
            return $this->update($id, $parsedParams);
        }
        return $this->update($id, $content);
    }

}

