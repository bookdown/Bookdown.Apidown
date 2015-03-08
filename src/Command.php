<?php
namespace Bookdown\Apidown;

use Aura\Cli\Context;
use Aura\Cli\Stdio;

class Command
{
    protected $context;
    protected $collector;

    public function __construct(
        Context $context,
        Stdio $stdio,
        Collector $collector
    ) {
        $this->context = $context;
        $this->stdio = $stdio;
        $this->collector = $collector;
    }

    public function __invoke()
    {
        try {
            $file = $this->init();
            $classes = $this->collector->__invoke($file);
            $this->dump($classes);
            return 0;
        } catch (AnyException $e) {
            $this->stdio->errln($e->getMessage());
            $code = $e->getCode() ? $e->getCode() : 1;
            return $code;
        }
    }

    protected function init()
    {
        $file = $this->context->argv->get(1);
        if (! $file) {
            throw new Exception(
                "Please enter the path to a structure.xml file as the first argument."
            );
        }
        return $file;
    }

    protected function dump($classes)
    {
        $export = var_export($classes, true);
        $export = preg_replace("/\=\>\s*\n\s*/m", "=> ", $export);
        $this->stdio->outln($export);
    }
}
