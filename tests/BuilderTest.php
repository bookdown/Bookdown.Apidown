<?php
namespace Bookdown\Apidown;

class BuilderTest extends \PHPUnit_Framework_TestCase
{
    protected $builder;

    protected function setUp()
    {
        $this->builder = new Builder();
    }

    protected function newXml($string)
    {
        return simplexml_load_string($string);
    }

    public function testIsDeprecated()
    {
        $xml = $this->newXml('
            <foo>
                <docblock>
                    <tag name="deprecated" />
                </docblock>
            </foo>
        ');

        $this->assertTrue($this->builder->isDeprecated($xml));

        $xml = $this->newXml('<foo><docblock></docblock></foo>');
        $this->assertFalse($this->builder->isDeprecated($xml));
    }

    public function testGetReturn()
    {
        $xml = $this->newXml('
            <method>
                <docblock>
                  <tag name="return" description="Returns this." type="mixed">
                    <type>mixed</type>
                  </tag>
                </docblock>
            </method>
        ');

        $actual = $this->builder->getReturn($xml);
        $this->assertSame('Returns this.', $actual->summary);
        $this->assertSame('mixed', $actual->type);

        $xml = $this->newXml('
            <method>
                <docblock>
                </docblock>
            </method>
        ');

        $this->assertNull($this->builder->getReturn($xml));
    }

    public function testGetPropertyType()
    {
        $xml = $this->newXml('
            <property>
                <docblock>
                    <tag name="var">
                        <type>string</type>
                    </tag>
                </docblock>
            </property>
        ');

        $this->assertSame('string', $this->builder->getVarType($xml));

        $xml = $this->newXml('
            <property>
                <docblock>
                </docblock>
            </property>
        ');

        $this->assertNull($this->builder->getVarType($xml));
    }

    public function testGetImplements()
    {
        $xml = $this->newXml('
            <class>
                <implements>Foo</implements>
                <implements>Bar</implements>
            </class>
        ');


        $expect = array('Foo', 'Bar');
        $this->assertSame($expect, $this->builder->getImplements($xml));

        $xml = $this->newXml('
            <class>
            </class>
        ');

        $this->assertSame(array(), $this->builder->getImplements($xml));
    }

    public function testNewArgument()
    {
        $params = array(
            $this->newXml('<tag name="param" description="Bar" type="string" variable="$bar"><type>string</type></tag>'),
            $this->newXml('<tag name="param" description="Foo" type="array" variable="$foo"><type>array</type></tag>'),
            $this->newXml('<tag name="param" description="Baz" type="int" variable="$baz"><type>int</type></tag>'),
        );

        $xmlArgument = $this->newXml('
            <argument by_reference="true">
                <name>$foo</name>
                <default>array()</default>
                <type>array</type>
            </argument>
        ');

        $expect = array(
            'name' => '$foo',
            'summary' => 'Foo',
            'byReference' => true,
            'type' => 'array',
            'default' => 'array()',
        );
        $actual = (array) $this->builder->newArgument($xmlArgument, $params);
        $this->assertSame($expect, $actual);
    }

    public function testGetArguments()
    {
        $xmlMethod = $this->newXml('
            <method>
                <argument>
                    <name>$foo</name>
                    <default></default>
                    <type>string</type>
                </argument>
                <argument>
                    <name>$bar</name>
                    <default></default>
                    <type>int</type>
                </argument>
                <argument>
                    <name>$baz</name>
                    <default></default>
                    <type>array</type>
                </argument>
            </method>
        ');

        $expect = array(
            '$foo' => array(
                'name' => '$foo',
                'summary' => null,
                'byReference' => false,
                'type' => 'string',
                'default' => null,
            ),
            '$bar' => array(
                'name' => '$bar',
                'summary' => null,
                'byReference' => false,
                'type' => 'int',
                'default' => null,
            ),
            '$baz' => array(
                'name' => '$baz',
                'summary' => null,
                'byReference' => false,
                'type' => 'array',
                'default' => null,
            ),
        );

        $actual = $this->builder->getArguments($xmlMethod);

        $this->assertSame(array('$foo', '$bar', '$baz'), array_keys($actual));
        $this->assertSame($expect['$foo'], (array) $actual['$foo']);
        $this->assertSame($expect['$bar'], (array) $actual['$bar']);
        $this->assertSame($expect['$baz'], (array) $actual['$baz']);
    }

    public function testNewMethod()
    {
        $xmlMethod = $this->newXml('
            <method>
                <name>fooMethod</name>
                <docblock>
                    <description>Short summary.</description>
                    <long-description>Long narrative.</long-description>
                    <tag name="param" description="Bar" type="string" variable="$bar">
                        <type>string</type>
                    </tag>
                    <tag name="param" description="Foo" type="array" variable="$foo">
                        <type>array</type>
                    </tag>
                    <tag name="param" description="Baz" type="int" variable="$baz">
                        <type>int</type>
                    </tag>
                </docblock>
                <argument>
                    <name>$bar</name>
                    <default></default>
                    <type>int</type>
                </argument>
                <argument>
                    <name>$foo</name>
                    <default></default>
                    <type>string</type>
                </argument>
                <argument>
                    <name>$baz</name>
                    <default></default>
                    <type>array</type>
                </argument>
            </method>
        ');

        $expect_method = array(
            'name' => 'fooMethod',
            'inheritedFrom' => null,
            'isDeprecated' => false,
            'summary' => 'Short summary.',
            'narrative' => 'Long narrative.',
            'return' => null,
            'visibility' => null,
            'final' => null,
            'abstract' => null,
            'static' => null,
        );

        $expect_arguments = array(
            '$bar' => array(
                'name' => '$bar',
                'summary' => 'Bar',
                'byReference' => false,
                'type' => 'int',
                'default' => null,
            ),
            '$foo' => array(
                'name' => '$foo',
                'summary' => 'Foo',
                'byReference' => false,
                'type' => 'string',
                'default' => null,
            ),
            '$baz' => array(
                'name' => '$baz',
                'summary' => 'Baz',
                'byReference' => false,
                'type' => 'array',
                'default' => null,
            ),
        );

        $actual_method = $this->builder->newMethod($xmlMethod);
        $actual_arguments = $actual_method->arguments;
        unset($actual_method->arguments);

        $this->assertSame($expect_method, (array) $actual_method);
        $this->assertSame(array('$bar', '$foo', '$baz'), array_keys($actual_arguments));
        $this->assertSame($expect_arguments['$bar'], (array) $actual_arguments['$bar']);
        $this->assertSame($expect_arguments['$foo'], (array) $actual_arguments['$foo']);
        $this->assertSame($expect_arguments['$baz'], (array) $actual_arguments['$baz']);
    }

    public function testGetMethods()
    {
        $xml = $this->newXml('
            <class>
                <method>
                    <name>fooMethod</name>
                </method>
                <method>
                    <name>barMethod</name>
                </method>
                <method>
                    <name>bazMethod</name>
                </method>
            </class>
        ');

        $expect = array(
            'fooMethod' => array(
                'name' => 'fooMethod',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'return' => null,
                'visibility' => null,
                'final' => null,
                'abstract' => null,
                'static' => null,
                'arguments' => array(),
            ),
            'barMethod' => array(
                'name' => 'barMethod',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'return' => null,
                'visibility' => null,
                'final' => null,
                'abstract' => null,
                'static' => null,
                'arguments' => array(),
            ),
            'bazMethod' => array(
                'name' => 'bazMethod',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'return' => null,
                'visibility' => null,
                'final' => null,
                'abstract' => null,
                'static' => null,
                'arguments' => array(),
            ),
        );

        $actual = $this->builder->getMethods($xml);
        $this->assertSame(array('fooMethod', 'barMethod', 'bazMethod'), array_keys($actual));
        $this->assertSame($expect['fooMethod'], (array) $actual['fooMethod']);
        $this->assertSame($expect['barMethod'], (array) $actual['barMethod']);
        $this->assertSame($expect['bazMethod'], (array) $actual['bazMethod']);
    }

    public function testNewProperty()
    {
        $xml = $this->newXml('
            <property static="false" visibility="public">
                <name>$foo</name>
                <default></default>
                <docblock>
                    <description>Foo summary.</description>
                    <long-description>Foo narrative.</long-description>
                    <tag name="var" line="43" description="" type="string" variable="">
                        <type>string</type>
                    </tag>
                </docblock>
            </property>
        ');

        $expect = array(
            'name' => '$foo',
            'inheritedFrom' => null,
            'isDeprecated' => false,
            'summary' => 'Foo summary.',
            'narrative' => 'Foo narrative.',
            'type' => 'string',
            'visibility' => 'public',
            'static' => null,
            'default' => null,
        );

        $actual = (array) $this->builder->newProperty($xml);
        $this->assertSame($expect, $actual);
    }

    public function testGetProperties()
    {
        $xml = $this->newXml('
            <class>
                <property>
                    <name>$foo</name>
                </property>
                <property>
                    <name>$bar</name>
                </property>
                <property>
                    <name>$baz</name>
                </property>
            </class>
        ');

        $expect = array(
            '$foo' => array(
                'name' => '$foo',
                'inheritedFrom' => NULL,
                'isDeprecated' => false,
                'summary' => NULL,
                'narrative' => NULL,
                'type' => NULL,
                'visibility' => NULL,
                'static' => NULL,
                'default' => NULL,
            ),
                '$bar' => array(
                'name' => '$bar',
                'inheritedFrom' => NULL,
                'isDeprecated' => false,
                'summary' => NULL,
                'narrative' => NULL,
                'type' => NULL,
                'visibility' => NULL,
                'static' => NULL,
                'default' => NULL,
            ),
            '$baz' => array(
                'name' => '$baz',
                'inheritedFrom' => NULL,
                'isDeprecated' => false,
                'summary' => NULL,
                'narrative' => NULL,
                'type' => NULL,
                'visibility' => NULL,
                'static' => NULL,
                'default' => NULL,
            ),
        );

        $actual = $this->builder->getProperties($xml);
        $this->assertSame(array('$foo', '$bar', '$baz'), array_keys($actual));
        $this->assertSame($expect['$foo'], (array) $actual['$foo']);
        $this->assertSame($expect['$bar'], (array) $actual['$bar']);
        $this->assertSame($expect['$baz'], (array) $actual['$baz']);
    }

    public function testNewConstant()
    {
        $xml = $this->newXml('
            <constant>
                <name>FOO</name>
                <value>0</value>
                <docblock>
                    <description>FOO summary.</description>
                    <long-description>FOO narrative.</long-description>
                </docblock>
            </constant>
        ');

        $expect = array (
            'name' => 'FOO',
            'inheritedFrom' => null,
            'isDeprecated' => false,
            'summary' => 'FOO summary.',
            'narrative' => 'FOO narrative.',
            'type' => null,
            'value' => '0',
        );

        $actual = $this->builder->newConstant($xml);
        $this->assertSame($expect, (array) $actual);
    }

    public function testGetConstants()
    {
        $xml = $this->newXml('
            <class>
                <constant>
                    <name>FOO</name>
                </constant>
                <constant>
                    <name>BAR</name>
                </constant>
                <constant>
                    <name>BAZ</name>
                </constant>
            </class>
        ');

        $expect = array(
            'FOO' => array(
                'name' => 'FOO',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'type' => null,
                'value' => null,
            ),
            'BAR' => array(
                'name' => 'BAR',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'type' => null,
                'value' => null,
            ),
            'BAZ' => array(
                'name' => 'BAZ',
                'inheritedFrom' => null,
                'isDeprecated' => false,
                'summary' => null,
                'narrative' => null,
                'type' => null,
                'value' => null,
            ),
        );

        $actual = $this->builder->getConstants($xml);
        $this->assertSame(array('FOO', 'BAR', 'BAZ'), array_keys($actual));
        $this->assertSame($expect['FOO'], (array) $actual['FOO']);
        $this->assertSame($expect['BAR'], (array) $actual['BAR']);
        $this->assertSame($expect['BAZ'], (array) $actual['BAZ']);
    }
}
