<?php
namespace Jankx\SearchEngine\UI\Contracts;

interface Component
{
    /**
     * Render the component HTML
     *
     * @param array $atts
     * @return string
     */
    public function render($atts = []);

    /**
     * Get the component unique ID
     *
     * @return string
     */
    public function getName();
}
