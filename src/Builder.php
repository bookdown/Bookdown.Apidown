<?php
namespace Bookdown\Apidown;

use SimpleXmlElement;

class Builder
{
    public function newClass(SimpleXmlElement $xmlClass)
    {
        $class = (object) array(
            'fullName'      => ltrim((string) $xmlClass->full_name, '\\'),
            'package'       => $this->getAttributeValue($xmlClass, 'package'),
            'isDeprecated'  => $this->isDeprecated($xmlClass),
            'summary'       => $this->getDocblockTagValue($xmlClass, 'description'),
            'narrative'     => $this->getDocblockTagValue($xmlClass, 'long-description'),
            'namespace'     => $this->getAttributeValue($xmlClass, 'namespace'),
            'final'         => $this->getKeyword($xmlClass, 'final'),
            'abstract'      => $this->getKeyword($xmlClass, 'abstract'),
            'type'          => (string) $xmlClass->getName(),
            'name'          => $this->getTagValue($xmlClass, 'name'),
            'extends'       => $this->getTagValue($xmlClass, 'extends'),
            'implements'    => $this->getImplements($xmlClass),
            'constants'     => array(),
            'properties'    => array(),
            'methods'       => array(),
            'inherited'     => (object) array(
                'constants' => array(),
                'properties' => array(),
                'methods' => array(),
            ),
        );

        $this->addParts('constants', $class, $xmlClass);
        $this->addParts('properties', $class, $xmlClass);
        $this->addParts('methods', $class, $xmlClass);

        return $class;
    }

    public function addParts($name, $class, SimpleXmlElement $xmlClass)
    {
        $method = "get{$name}";
        $parts = $this->$method($xmlClass);
        foreach ($parts as $part) {
            $this->addPart($name, $class, $part);
        }
        ksort($class->$name);
        ksort($class->inherited->$name);
    }

    protected function addPart($name, $class, $part)
    {
        if ($part->inheritedFrom) {
            $class->inherited->{$name}[$part->name] = $part;
            return;
        }
        $class->{$name}[$part->name] = $part;
    }

    public function getConstants(SimpleXmlElement $xmlClass)
    {
        $constants = array();
        foreach ($xmlClass->constant as $xmlConstant) {
            $constant = $this->newConstant($xmlConstant);
            $constants[$constant->name] = $constant;
        }
        return $constants;
    }

    public function newConstant(SimpleXmlElement $xmlConstant)
    {
        return (object) array(
            'name'          => (string) $xmlConstant->name,
            'inheritedFrom' => $this->getTagValue($xmlConstant, 'inherited_from'),
            'isDeprecated'  => $this->isDeprecated($xmlConstant),
            'summary'       => $this->getDocblockTagValue($xmlConstant, 'description'),
            'narrative'     => $this->getDocblockTagValue($xmlConstant, 'long-description'),
            'type'          => $this->getVarType($xmlConstant),
            'value'         => $this->getTagValue($xmlConstant, 'value'),
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
            'inheritedFrom' => $this->getTagValue($xmlProperty, 'inherited_from'),
            'isDeprecated'  => $this->isDeprecated($xmlProperty),
            'summary'       => $this->getDocblockTagValue($xmlProperty, 'description'),
            'narrative'     => $this->getDocblockTagValue($xmlProperty, 'long-description'),
            'type'          => $this->getVarType($xmlProperty),
            'visibility'    => $this->getAttributeValue($xmlProperty, 'visibility'),
            'static'        => $this->getKeyword($xmlProperty, 'static'),
            'default'       => $this->getTagValue($xmlProperty, 'default'),
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
            'inheritedFrom' => $this->getTagValue($xmlMethod, 'inherited_from'),
            'isDeprecated'  => $this->isDeprecated($xmlMethod),
            'summary'       => $this->getDocblockTagValue($xmlMethod, 'description'),
            'narrative'     => $this->getDocblockTagValue($xmlMethod, 'long-description'),
            'return'        => $this->getReturn($xmlMethod),
            'visibility'    => $this->getAttributeValue($xmlMethod, 'visibility'),
            'final'         => $this->getKeyword($xmlMethod, 'final'),
            'abstract'      => $this->getKeyword($xmlMethod, 'abstract'),
            'static'        => $this->getKeyword($xmlMethod, 'static'),
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
        $byReference = $this->getAttributeValue($xmlArgument, 'by_reference');
        return (object) array(
            'name'          => $name,
            'summary'       => $this->getArgumentSummary($params, $name),
            'byReference'   => ($byReference === 'true'),
            'type'          => $this->getTagValue($xmlArgument, 'type'),
            'default'       => $this->getTagValue($xmlArgument, 'default'),
        );
    }

    protected function getArgumentSummary(array $params, $name)
    {
        foreach ($params as $param) {
            $variable = $this->getAttributeValue($param, 'variable');
            if ($variable === $name) {
                return $this->getAttributeValue($param, 'description');
            }
        }
    }

    public function getImplements(SimpleXmlElement $xmlClass)
    {
        $implements = array();
        foreach ((array) $xmlClass->implements as $interface) {
            $interface = ltrim((string) $interface, '\\');
            if ($interface !== '') {
                $implements[] = $interface;
            }
        }
        return $implements;
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

    public function getVarType(SimpleXmlElement $xmlProperty)
    {
        $var = $this->getDocblockTag($xmlProperty, array('name' => 'var'));
        if ($var) {
            return (string) $var->type;
        }
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

    protected function getDocblockTagValue($xml, $name)
    {
        return $this->getTagValue($xml->docblock, $name);
    }

    protected function getKeyword(SimpleXmlElement $xml, $key)
    {
        return $this->getAttributeValue($xml, $key) === 'true' ? $key : null;
    }

    protected function getTagValue(SimpleXmlElement $xml, $name)
    {
        $value = (string) $xml->$name;
        if ($value !== '') {
            return $value;
        }
    }

    protected function getAttributeValue(SimpleXmlElement $xml, $name)
    {
        $value = (string) $xml[$name];
        if ($value !== '') {
            return $value;
        }
    }
}
