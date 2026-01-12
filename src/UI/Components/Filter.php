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
            if (!isset($atts['terms_' . $tax])) {
                $tax_data[$tax] = [
                    'tax_obj' => get_taxonomy($tax),
                    'terms' => [],
                ];
                continue;
            }

            $taxIds = explode(',', $atts['terms_' . $tax]);
            $tax_data[$tax] = [
                'tax_obj' => get_taxonomy($tax),
                'terms' => get_terms([
                    'taxonomy' => $tax,
                    'include' => $taxIds,
                ])
            ];
            $hasItems = true;
        }
        return $this->render_template('filters', [
            'tax_data' => $tax_data,
            'atts' => $atts,
            'has_items' => $hasItems,
        ]);
    }
}
