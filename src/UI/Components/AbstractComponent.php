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
            return 'Jankx Template Engine is not initialized.';
        }
        try {
            $content = jankx_template($defaultPath, $template, $data, false);
            if (empty($content) && (is_admin() || (defined('DOING_AJAX') && DOING_AJAX))) {
                return sprintf('[Jankx %s Component]', ucfirst(is_array($template) ? reset($template) : $template));
            }
            return $content;
        } catch (\Exception $e) {
            return 'Template Error in ' . (is_array($template) ? reset($template) : $template) . ': ' . $e->getMessage();
        }
    }
}
