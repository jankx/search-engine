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

        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $atts['hide_empty'] ?? false,
        ];

        // Check if specific terms are selected in UX Builder options
        // UX Builder saves dynamic options as keys like 'terms_category', 'terms_post_tag'
        $selected_terms_key = 'terms_' . $taxonomy;
        if (!empty($atts[$selected_terms_key])) {
            $selected_slugs = $atts[$selected_terms_key];
            if (is_string($selected_slugs)) {
                $selected_slugs = explode(',', $selected_slugs);
            }
            $args['slug'] = $selected_slugs;
            $args['orderby'] = 'slug__in'; // Preserve selection order if needed, or stick to name
        }

        $terms = get_terms($args);

        return $this->render_template('filter', [
            'taxonomy' => $taxonomy,
            'tax_obj' => $tax_obj,
            'terms' => $terms,
            'atts' => $atts,
        ]);
    }
}
