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

    public function testGetKeywords()
    {
        $xml = $this->newXml('<foo final="true" abstract="true" static="true"></foo>');
        $this->assertSame('final', $this->builder->getFinal($xml));
        $this->assertSame('abstract', $this->builder->getAbstract($xml));
        $this->assertSame('static', $this->builder->getStatic($xml));

        $xml = $this->newXml('<foo final="false" abstract="false" static="false"></foo>');
        $this->assertNull($this->builder->getFinal($xml));
        $this->assertNull($this->builder->getAbstract($xml));
        $this->assertNull($this->builder->getStatic($xml));

        $xml = $this->newXml('<foo></foo>');
        $this->assertNull($this->builder->getFinal($xml));
        $this->assertNull($this->builder->getAbstract($xml));
        $this->assertNull($this->builder->getStatic($xml));
    }

    public function testGetInheritedFrom()
    {
        $xml = $this->newXml('<foo><inherited_from>Bar</inherited_from></foo>');
        $this->assertSame('Bar', $this->builder->getInheritedFrom($xml));

        $xml = $this->newXml('<foo><inherited_from></inherited_from></foo>');
        $this->assertNull($this->builder->getInheritedFrom($xml));

        $xml = $this->newXml('<foo></foo>');
        $this->assertNull($this->builder->getInheritedFrom($xml));
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

    public function testGetSummary()
    {
        $xml = $this->newXml('
            <foo>
                <docblock>
                    <description>Summary line.</description>
                </docblock>
            </foo>
        ');

        $this->assertSame('Summary line.', $this->builder->getSummary($xml));

        $xml = $this->newXml('
            <foo>
                <docblock>
                </docblock>
            </foo>
        ');

        $this->assertNull($this->builder->getSummary($xml));
    }

    public function testGetNarrative()
    {
        $xml = $this->newXml('
            <foo>
                <docblock>
                    <long-description>Narrative lines.</long-description>
                </docblock>
            </foo>
        ');

        $this->assertSame('Narrative lines.', $this->builder->getNarrative($xml));

        $xml = $this->newXml('
            <foo>
                <docblock>
                </docblock>
            </foo>
        ');

        $this->assertNull($this->builder->getNarrative($xml));
    }

    public function testGetVisibility()
    {
        $xml = $this->newXml('<foo visibility="public" />');
        $this->assertSame('public', $this->builder->getVisibility($xml));

        $xml = $this->newXml('<foo />');
        $this->assertNull($this->builder->getVisibility($xml));
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

        $this->assertSame('string', $this->builder->getPropertyType($xml));

        $xml = $this->newXml('
            <property>
                <docblock>
                </docblock>
            </property>
        ');

        $this->assertNull($this->builder->getPropertyType($xml));
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
                'default' => '',
            ),
            '$bar' => array(
                'name' => '$bar',
                'summary' => null,
                'byReference' => false,
                'type' => 'int',
                'default' => '',
            ),
            '$baz' => array(
                'name' => '$baz',
                'summary' => null,
                'byReference' => false,
                'type' => 'array',
                'default' => '',
            ),
        );

        $actual = $this->builder->getArguments($xmlMethod);

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
                'default' => '',
            ),
            '$foo' => array(
                'name' => '$foo',
                'summary' => 'Foo',
                'byReference' => false,
                'type' => 'string',
                'default' => '',
            ),
            '$baz' => array(
                'name' => '$baz',
                'summary' => 'Baz',
                'byReference' => false,
                'type' => 'array',
                'default' => '',
            ),
        );

        $actual_method = $this->builder->newMethod($xmlMethod);
        $actual_arguments = $actual_method->arguments;
        unset($actual_method->arguments);

        $this->assertSame($expect_method, (array) $actual_method);
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
        $this->assertSame($expect['fooMethod'], (array) $actual['fooMethod']);
        $this->assertSame($expect['barMethod'], (array) $actual['barMethod']);
        $this->assertSame($expect['bazMethod'], (array) $actual['bazMethod']);
    }

    public function testNewProperty()
    {

    }
}
