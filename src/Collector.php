<?php
namespace Bookdown\Apidown;

use SimpleXmlElement;

class Collector
{
    protected $classes;

    protected $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function __invoke(SimpleXmlElement $xml)
    {
        $this->classes = array();

        $xmlClasses = $xml->xpath('file/class|file/interface|file/trait');
        foreach ($xmlClasses as $xmlClass) {
            $class = $this->builder->newClass($xmlClass);
            $this->classes[$class->fullName] = $class;
        }

        ksort($this->classes);
        return $this->classes;
    }
}
