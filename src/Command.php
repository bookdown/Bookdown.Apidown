<?php
namespace Bookdown\Apidown;

class Command
{
    protected $file;

    public function __construct($argv)
    {
        if (! isset($argv[1])) {
            echo "Pass a structure.xml path as the first argument.";
            return 1;
        }
        $this->file = $argv[1];
    }

    public function __invoke()
    {
        echo "Parsing {$this->file}" . PHP_EOL;
        $xml = simplexml_load_file($this->file);
        $parser = new Collector(new Builder);
        $classes = $parser($xml);

        $output = var_export($classes, true);
        $output = preg_replace("/\=\>\s*\n\s*/m", "=> ", $output);
        echo $output;
        return 0;
    }
}
