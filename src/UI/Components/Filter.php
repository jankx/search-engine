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
        return $this->render_template('filters', [
            'tax_data' => apply_filters('jankx/search-engine/filter/datas', $tax_data, $this),
            'atts' => $atts,
            'has_items' => $hasItems,
        ]);
    }
}
