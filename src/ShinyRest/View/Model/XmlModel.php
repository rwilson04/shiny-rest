<?php

namespace ShinyRest\View\Model;

use Traversable;
use Zend\Stdlib\ArrayUtils;
use ShinyLib\Xml\Array2XmlTrait;

class XmlModel extends \Zend\View\Model\ViewModel
{
	use Array2XmlTrait;

    /**
     * Serialize to XML
     *
     * @return string
     */
    public function serialize()
    {
        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = ArrayUtils::iteratorToArray($variables);
        }
		//set root element to <problem> if api-problem+xml type
		if (array_key_exists('title', $variables) &&
			array_key_exists('httpStatus', $variables) &&
			array_key_exists('describedBy', $variables) )
		{
			$xml = $this->createXML('problem', $variables);	
		}
		else
		{
			$xml = $this->createXML('root', $variables);	
		}
		#echo '<pre>';
		#print_r($variables);
		#array_walk_recursive($variables, function($value, $key) {
			#echo "$key=>$value <br />";	
		#});

		return $xml->saveXML();
		#return ($xml->asXML());
    }

}
