<?php
namespace Jankx\SearchEngine\UI\Components;

class Sorter extends AbstractComponent
{
    protected $name = 'sorter';

    public function render($atts = [])
    {
        return $this->render_template('sorter', ['atts' => $atts]);
    }
}
