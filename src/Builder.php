<?php
namespace Bookdown\Apidown;

use SimpleXmlElement;

class Builder
{
    public function newClass(SimpleXmlElement $xmlClass)
    {
        $class = (object) array(
            'fullName'      => ltrim((string) $xmlClass->full_name, '\\'),
            'package'       => (string) $xmlClass['package'],
            'subpackage'    => (string) $xmlClass['subpackage'],
            'deprecated'    => $this->isDeprecated($xmlClass),
            'summary'       => (string) $xmlClass->docblock->description,
            'narrative'     => (string) $xmlClass->docblock->{"long-description"},
            'namespace'     => (string) $xmlClass['namespace'],
            'final'         => $this->getFinal($xmlClass),
            'abstract'      => $this->getAbstract($xmlClass),
            'type'          => (string) $xmlClass->getName(),
            'name'          => (string) $xmlClass->name,
            'extends'       => $this->getParents($xmlClass, 'extends'),
            'implements'    => $this->getParents($xmlClass, 'implements'),
            'constants'     => array(),
            'properties'    => array(),
            'methods'       => array(),
            'inherited'     => (object) array(
                'constants' => array(),
                'properties' => array(),
                'methods' => array(),
            ),
        );

        $this->add('constants', $class, $xmlClass);
        $this->add('properties', $class, $xmlClass);
        $this->add('methods', $class, $xmlClass);

        return $class;
    }

    public function add($name, $class, SimpleXmlElement $xmlClass)
    {
        $method = "get{$name}";
        $parts = $this->$method($xmlClass);
        foreach ($parts as $part) {
            if ($part->inheritedFrom) {
                $class->inherited->{$name}[$part->name] = $part;
            } else {
                $class->{$name}[$part->name] = $part;
            }
        }

        ksort($class->$name);
        ksort($class->inherited->$name);
    }

    public function getParents(SimpleXmlElement $xmlClass, $type)
    {
        $parents = array();
        foreach ((array) $xmlClass->$type as $parent) {
            $parent = ltrim((string) $parent, '\\');
            if ($parent) {
                $parents[] = $parent;
            }
        }
        return $parents;
    }

    public function getConstants(SimpleXmlElement $xmlClass)
    {
        $constants = array();
        foreach ($xmlClass->constant as $xmlConstant) {
            $constant = $this->newConstant($xmlConstant);
            $constants[$constant['name']] = $constant;
        }
        return $constants;
    }

    public function newConstant(SimpleXmlElement $xmlConstant)
    {
        return (object) array(
            'name'          => (string) $constant->name,
            'inheritedFrom' => $this->getInheritedFrom($xmlConstant),
            'summary'       => (string) $constant->docblock->description,
            'narrative'     => (string) $constant->docblock->{"long-description"},
            'value'         => (string) $constant->value,
            'deprecated'    => $this->isDeprecated($xmlConstant),
        );
    }

    public function getProperties(SimpleXmlElement $xmlClass)
    {
        $properties = array();
        foreach ($xmlClass->property as $xmlProperty) {
            $property = $this->newProperty($xmlProperty);
            $properties[$property->name] = $property;
        }
        return $properties;
    }

    /**
     * @todo @property-read, @property-write
     */
    public function newProperty(SimpleXmlElement $xmlProperty)
    {
        $type = 'unknown';
        $var = $this->getDocblockTag($xmlProperty, array('name' => 'var'));
        if ($var) {
            $type = (string) $var->type;
        }

        return (object) array(
            'name'          => (string) $xmlProperty->name,
            'inheritedFrom' => $this->getInheritedFrom($xmlProperty),
            'type'          => $type,
            'default'       => (string) $xmlProperty->default,
            'summary'       => (string) $xmlProperty->docblock->description,
            'narrative'     => (string) $xmlProperty->docblock->{"long-description"},
            'visibility'    => (string) $xmlProperty['visibility'],
            'static'        => $this->getStatic($xmlProperty),
            'deprecated'    => $this->isDeprecated($xmlProperty),
        );
    }

    public function getMethods(SimpleXmlElement $xmlClass)
    {
        $methods = array();
        foreach ($xmlClass->method as $xmlMethod) {
            $method = $this->newMethod($xmlMethod);
            $methods[$method->name] = $method;
        }
        return $methods;
    }

    /**
     * @todo @throws
     */
    public function newMethod(SimpleXmlElement $xmlMethod)
    {
        $return = $this->getDocblockTag($xmlMethod, array('name' => 'return'));
        if ($return) {
            $return = (string) $return['type'];
        }

        return (object) array(
            'name'          => (string) $xmlMethod->name,
            'inheritedFrom' => $this->getInheritedFrom($xmlMethod),
            'summary'       => (string) $xmlMethod->docblock->description,
            'narrative'     => (string) $xmlMethod->docblock->{"long-description"},
            'visibility'    => (string) $xmlMethod['visibility'],
            'final'         => $this->getFinal($xmlMethod),
            'abstract'      => $this->getAbstract($xmlMethod),
            'static'        => $this->getStatic($xmlMethod),
            'return'        => $return,
            'deprecated'    => $this->isDeprecated($xmlMethod),
            'arguments'     => $this->getArguments($xmlMethod),
        );
    }

    public function getArguments(SimpleXmlElement $xmlMethod)
    {
        $arguments = array();
        foreach ($xmlMethod->argument as $xmlArgument) {
            $argument = $this->newArgument($xmlMethod, $xmlArgument);
            $arguments[$argument->name] = $argument;
        }
        return $arguments;
    }

    public function newArgument(
        SimpleXmlElement $xmlMethod,
        SimpleXmlElement $xmlArgument
    ) {
        $argument = (object) array(
            'name'      => (string) $xmlArgument->name,
            'type'      => (string) $xmlArgument->type,
            'default'   => (string) $xmlArgument->default,
            'summary'   => null,
        );

        $param = $this->getDocblockTag($xmlMethod, array(
            'name' => 'param',
            'variable' => $xmlArgument->name
        ));

        if (! $param) {
            return $argument;
        }

        $param_type = (string) $param['type'];
        if (! $argument->type && $param_type) {
            $argument->type = $param_type;
        }

        $param_description = (string) $param['description'];
        if ($param_description) {
            $argument->summary = $param_description;
        }

        return $argument;
    }

    protected function getFinal(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'final');
    }

    protected function getAbstract(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'abstract');
    }

    protected function getStatic(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'static');
    }

    protected function isDeprecated(SimpleXmlElement $xml)
    {
        return (bool) $this->getDocblockTag($xml, array(
            'name' => 'deprecated'
        ));
    }

    protected function getInheritedFrom(SimpleXmlElement $xml)
    {
        $value = (string) $xml->inherited_from;
        if ($value) {
            return $value;
        }
    }

    protected function getKeyword(SimpleXmlElement $xml, $key)
    {
        return ((string) $xml[$key]) === 'true' ? $key : null;
    }

    protected function getDocblockTag(SimpleXmlElement $xml, $vars)
    {
        $tags = $this->getDocblockTags($xml, $vars);
        if ($tags) {
            return $tags[0];
        }
    }

    protected function getDocblockTags(SimpleXmlElement $xml, array $vars)
    {
        $spec = array();
        foreach ($vars as $key => $val) {
            $spec[] = "@{$key}=\"{$val}\"";
        }
        $spec = implode(' and ', $spec);

        $tags = $xml->xpath("docblock/tag[$spec]");
        if ($tags) {
            return $tags;
        }

        return array();
    }
}
