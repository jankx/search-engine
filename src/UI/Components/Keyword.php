<?php
namespace Jankx\SearchEngine\UI\Components;

class Keyword extends AbstractComponent
{
    protected $name = 'keyword';

    public function render($atts = [])
    {
        return $this->render_template('keyword', ['atts' => $atts]);
    }
}
