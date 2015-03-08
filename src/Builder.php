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
            'isDeprecated'  => $this->isDeprecated($xmlClass),
            'summary'       => $this->getSummary($xmlClass),
            'narrative'     => $this->getNarrative($xmlClass),
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
            'isDeprecated'  => $this->isDeprecated($xmlConstant),
            'summary'       => $this->getSummary($xmlConstant),
            'narrative'     => $this->getNarrative($xmlConstant),
            'value'         => (string) $constant->value,
        );
    }

    /**
     * @todo @property-read, @property-write
     */
    public function getProperties(SimpleXmlElement $xmlClass)
    {
        $properties = array();
        foreach ($xmlClass->property as $xmlProperty) {
            $property = $this->newProperty($xmlProperty);
            $properties[$property->name] = $property;
        }
        return $properties;
    }

    public function newProperty(SimpleXmlElement $xmlProperty)
    {
        return (object) array(
            'name'          => (string) $xmlProperty->name,
            'inheritedFrom' => $this->getInheritedFrom($xmlProperty),
            'isDeprecated'  => $this->isDeprecated($xmlProperty),
            'summary'       => $this->getSummary($xmlProperty),
            'narrative'     => $this->getNarrative($xmlProperty),
            'type'          => $this->getPropertyType($xmlProperty),
            'visibility'    => $this->getVisibility($xmlProperty),
            'static'        => $this->getStatic($xmlProperty),
            'default'       => (string) $xmlProperty->default,
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
        return (object) array(
            'name'          => (string) $xmlMethod->name,
            'inheritedFrom' => $this->getInheritedFrom($xmlMethod),
            'isDeprecated'  => $this->isDeprecated($xmlMethod),
            'summary'       => $this->getSummary($xmlMethod),
            'narrative'     => $this->getNarrative($xmlMethod),
            'return'        => $this->getReturn($xmlMethod),
            'visibility'    => $this->getVisibility($xmlMethod),
            'final'         => $this->getFinal($xmlMethod),
            'abstract'      => $this->getAbstract($xmlMethod),
            'static'        => $this->getStatic($xmlMethod),
            'arguments'     => $this->getArguments($xmlMethod),
        );
    }

    public function getArguments(SimpleXmlElement $xmlMethod)
    {
        $arguments = array();
        $params = $this->getDocblockTags($xmlMethod, array('name' => 'param'));
        foreach ($xmlMethod->argument as $xmlArgument) {
            $argument = $this->newArgument($xmlArgument, $params);
            $arguments[$argument->name] = $argument;
        }
        return $arguments;
    }

    public function newArgument(SimpleXmlElement $xmlArgument, array $params)
    {
        $name = (string) $xmlArgument->name;

        $summary = null;
        foreach ($params as $param) {
            if ($name === (string) $param['variable']) {
                $summary = (string) $param['description'];
            }
        }

        $byReference = ((string) $xmlArgument['by_reference']) === 'true';

        return (object) array(
            'name'          => $name,
            'summary'       => $summary,
            'byReference'   => $byReference,
            'type'          => (string) $xmlArgument->type,
            'default'       => (string) $xmlArgument->default,
        );
    }

    public function getInheritedFrom(SimpleXmlElement $xml)
    {
        $value = (string) $xml->inherited_from;
        if ($value) {
            return $value;
        }
    }

    public function getFinal(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'final');
    }

    public function getAbstract(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'abstract');
    }

    public function getStatic(SimpleXmlElement $xml)
    {
        return $this->getKeyword($xml, 'static');
    }

    public function isDeprecated(SimpleXmlElement $xml)
    {
        return (bool) $this->getDocblockTag($xml, array(
            'name' => 'deprecated'
        ));
    }

    public function getReturn(SimpleXmlElement $xmlMethod)
    {
        $return = $this->getDocblockTag($xmlMethod, array('name' => 'return'));
        if ($return) {
            return (object) array(
                'type' => (string) $return['type'],
                'summary' => (string) $return['description'],
            );
        }
    }

    public function getVisibility(SimpleXmlElement $xml)
    {
        $value = (string) $xml['visibility'];
        if ($value) {
            return $value;
        }
    }

    public function getSummary(SimpleXmlElement $xml)
    {
        $value = (string) $xml->docblock->description;
        if ($value) {
            return $value;
        }
    }

    public function getNarrative(SimpleXmlElement $xml)
    {
        $value = (string) $xml->docblock->{'long-description'};
        if ($value) {
            return $value;
        }
    }

    public function getPropertyType(SimpleXmlElement $xmlProperty)
    {
        $var = $this->getDocblockTag($xmlProperty, array('name' => 'var'));
        if ($var) {
            return (string) $var->type;
        }
    }

    protected function getKeyword(SimpleXmlElement $xml, $key)
    {
        return ((string) $xml[$key]) === 'true' ? $key : null;
    }

    protected function getDocblockTag(SimpleXmlElement $xml, array $attrs)
    {
        $tags = $this->getDocblockTags($xml, $attrs);
        if ($tags) {
            return $tags[0];
        }
    }

    protected function getDocblockTags(SimpleXmlElement $xml, array $attrs)
    {
        $query = array();
        foreach ($attrs as $key => $val) {
            $query[] = "@{$key}=\"{$val}\"";
        }
        $query = implode(' and ', $query);

        // add error checking for `false` return
        return $xml->xpath("docblock/tag[$query]");
    }
}
