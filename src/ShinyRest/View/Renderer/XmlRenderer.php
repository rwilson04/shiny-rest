<?php
/**
 * This class adapted from Zend\View\Renderer\JsonRenderer
 *
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_View
 */

namespace ShinyRest\View\Renderer;

use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\View\Exception;
use Zend\View\Model\ModelInterface as Model;
use Zend\View\Renderer\RendererInterface as Renderer;
use Zend\View\Resolver\ResolverInterface as Resolver;

/**
 * XML renderer
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage Renderer
 */
class XmlRenderer implements Renderer, \Zend\View\Renderer\TreeRendererInterface
{
    /**
     * Whether or not to merge child models with no capture-to value set
     * @var bool
     */
    protected $mergeUnnamedChildren = false;

    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * Return the template engine object, if any
     *
     * If using a third-party template engine, such as Smarty, patTemplate,
     * phplib, etc, return the template engine object. Useful for calling
     * methods on these objects, such as for setting filters, modifiers, etc.
     *
     * @return mixed
     */
    public function getEngine()
    {
        return $this;
    }

    /**
     * Set the resolver used to map a template name to a resource the renderer may consume.
     *
     * @param  Resolver $resolver
     * @return Renderer
     */
    public function setResolver(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Set flag indicating whether or not to merge unnamed children
     *
     * @param  bool $mergeUnnamedChildren
     * @return XmlRenderer
     */
    public function setMergeUnnamedChildren($mergeUnnamedChildren)
    {
        $this->mergeUnnamedChildren = (bool) $mergeUnnamedChildren;
        return $this;
    }

    /**
     * Should we merge unnamed children?
     *
     * @return bool
     */
    public function mergeUnnamedChildren()
    {
        return $this->mergeUnnamedChildren;
    }

    /**
     * Renders values as XML
     *
     * @todo   Determine what use case exists for accepting both $nameOrModel and $values
     * @param  string|Model $nameOrModel The script/resource process, or a view model
     * @param  null|array|\ArrayAccess $values Values to use during rendering
     * @throws Exception\DomainException
     * @return string The script output.
     */
    public function render($nameOrModel, $values = null)
    {
		if ($nameOrModel instanceof \ShinyRest\View\Model\XmlModel)
		{
			return $nameOrModel->serialize();
		}
		#echo "<pre>";
		#print_r($nameOrModel);
		throw new \DomainException(get_class($nameOrModel) . " is not an instance of XmlModel");

    }

    /**
     * Can this renderer render trees of view models?
     *
     * Yes.
     *
     * @return true
     */
    public function canRenderTrees()
    {
        return true;
    }

    /**
     * Retrieve values from a model and recurse its children to build a data structure
     *
     * @param  Model $model
     * @return array
     */
    protected function recurseModel(Model $model)
    {
        $values = $model->getVariables();
        if ($values instanceof Traversable) {
            $values = ArrayUtils::iteratorToArray($values);
        }

        if (!$model->hasChildren()) {
            return $values;
        }

        $mergeChildren = $this->mergeUnnamedChildren();
        foreach ($model as $child) {
            $captureTo = $child->captureTo();
            if (!$captureTo && !$mergeChildren) {
                // We don't want to do anything with this child
                continue;
            }

            $childValues = $this->recurseModel($child);
            if ($captureTo) {
                // Capturing to a specific key
                //TODO please complete if append is true. must change old value to array and append to array?
                $values[$captureTo] = $childValues;
            } elseif ($mergeChildren) {
                // Merging values with parent
                $values = array_replace_recursive($values, $childValues);
            }
        }
        return $values;
    }
}
