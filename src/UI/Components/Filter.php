<?php
namespace Jankx\SearchEngine\UI\Components;

class Filter extends AbstractComponent
{
    protected $name = 'filter';

    public function render($atts = [])
    {
        $enabledTax = array_filter($atts, function ($value, $key) {
            return strpos($key, 'show_tax_') === 0 && $value === 'true';
        }, ARRAY_FILTER_USE_BOTH);

        $tax_data = [];
        $hasItems = false;

        foreach ($enabledTax as $key => $value) {
            $tax = str_replace('show_tax_', '', $key);
            $tax_args = [
                'taxonomy' => $tax,
                'hide_empty' => false,
            ];

            if (!empty($atts['terms_' . $tax])) {
                $tax_args['include'] = explode(',', $atts['terms_' . $tax]);
            }

            $tax_data[$tax] = [
                'tax_obj' => get_taxonomy($tax),
                'terms' => get_terms($tax_args)
            ];
            $hasItems = true;
        }

        $active_filters = isset($_GET['filter']) ? $_GET['filter'] : [];
        if (empty($active_filters)) {
            foreach ($enabledTax as $key => $value) {
                $tax = str_replace('show_tax_', '', $key);
                if (isset($_GET[$tax])) {
                    $active_filters[$tax] = (array) $_GET[$tax];
                }
            }
        }

        return $this->render_template('filters', [
            'tax_data' => $tax_data,
            'atts' => $atts,
            'has_items' => $hasItems,
            'active_filters' => $active_filters,
        ]);
    }
}
