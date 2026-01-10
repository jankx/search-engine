<?php
namespace Jankx\SearchEngine\UI\Components;

class Filter extends AbstractComponent
{
    protected $name = 'filter';

    public function render($atts = [])
    {
        $taxonomy = $atts['taxonomy'] ?? 'category';
        $tax_obj = get_taxonomy($taxonomy);

        if (!$tax_obj) {
            return '';
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => $atts['hide_empty'] ?? false,
        ]);

        return $this->render_template('filter', [
            'taxonomy' => $taxonomy,
            'tax_obj' => $tax_obj,
            'terms' => $terms,
            'atts' => $atts,
        ]);
    }
}
