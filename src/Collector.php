<?php
namespace Bookdown\Apidown;

use Aura\Cli\Stdio;
use Bookdown\Bookdown\Fsio;
use SimpleXmlElement;

class Collector
{
    protected $sdio;

    protected $fsio;

    protected $classes;

    protected $builder;

    public function __construct(
        Stdio $stdio,
        Fsio $fsio,
        Builder $builder
    ) {
        $this->stdio = $stdio;
        $this->fsio = $fsio;
        $this->builder = $builder;
    }

    public function __invoke($file)
    {
        $xml = $this->getXml($file);
        $this->setClasses($xml);
        return $this->classes;
    }

    protected function getXml($file)
    {
        $this->stdio->outln("Collecting API docs from '{$file}'");
        $string = $this->fsio->get($file);
        return simplexml_load_string($string);
    }

    protected function setClasses(SimpleXmlElement $xml)
    {
        $this->classes = array();
        $xmlClasses = $xml->xpath('file/class|file/interface|file/trait');
        foreach ($xmlClasses as $xmlClass) {
            $class = $this->builder->newClass($xmlClass);
            $this->classes[$class->fullName] = $class;
        }
        ksort($this->classes);
    }
}
