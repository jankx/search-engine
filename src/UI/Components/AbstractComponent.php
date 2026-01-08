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

    protected function get_template($template_name, $data = [])
    {
        // Simple template loader logic could go here
        extract($data);
        ob_start();
        $template_path = sprintf('%s/templates/%s.php', dirname(__DIR__), $template_name);
        if (file_exists($template_path)) {
            include $template_path;
        }
        return ob_get_clean();
    }
}
