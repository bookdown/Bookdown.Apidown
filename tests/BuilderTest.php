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

    public function testNewArgument()
    {
        $params = array(
            $this->newXml('<tag name="param" description="Bar" type="string" variable="$bar"><type>string</type></tag>'),
            $this->newXml('<tag name="param" description="Foo" type="array" variable="$foo"><type>array</type></tag>'),
            $this->newXml('<tag name="param" description="Baz" type="int" variable="$baz"><type>int</type></tag>'),
        );

        $xmlArgument = $this->newXml('
            <argument by_reference="false">
                <name>$foo</name>
                <default>array()</default>
                <type>array</type>
            </argument>
        ');

        $expect = array(
            'name' => '$foo',
            'summary' => 'Foo',
            'byReference' => false,
            'type' => 'array',
            'default' => 'array()',
        );
        $actual = (array) $this->builder->newArgument($xmlArgument, $params);
        $this->assertSame($expect, $actual);
    }
}
