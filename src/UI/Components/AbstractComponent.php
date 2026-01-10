<?php
namespace Jankx\SearchEngine\UI\Components;

use Jankx\SearchEngine\UI\Contracts\Component;

abstract class AbstractComponent implements Component
{
    protected $name;

    public function getName()
    {
        return $this->name;
    }

    protected function render_template(string|array $template, array $data = [])
    {
        $defaultPath = sprintf('%s/views', dirname(dirname(dirname(__DIR__))));
        if (!function_exists('jankx_template')) {
            throw new \Exception('Jankx Template Engine is not initialized.');
        }
        return jankx_template($defaultPath, $template, $data, false);
    }
}
