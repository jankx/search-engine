<?php
namespace Jankx\SearchEngine\UI\Components;

class SelectedFilters extends AbstractComponent
{
    protected $name = 'selected_filters';

    public function render($atts = [])
    {
        $selected = [];
        $filters = isset($atts['filters']) ? $atts['filters'] : ($_GET['filter'] ?? []);
        $keyword = isset($atts['keyword']) ? $atts['keyword'] : ($_GET['s'] ?? ($_GET['q'] ?? ''));

        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $taxonomy => $term_ids) {
                if (!is_array($term_ids)) {
                    continue;
                }
                $tax_obj = get_taxonomy($taxonomy);
                $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;

                foreach ($term_ids as $term_id) {
                    $term = get_term($term_id, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $selected[] = [
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'taxonomy' => $taxonomy,
                            'taxonomy_label' => $tax_label,
                            'slug' => $term->slug,
                            'term' => $term
                        ];
                    }
                }
            }
        }

        if (!empty($keyword)) {
            $selected[] = [
                'id' => 'current_query',
                'name' => $keyword,
                'taxonomy' => 'search_keyword',
                'slug' => 'search_keyword',
                'term' => null
            ];
        }

        $has_selected = !empty($selected);

        return $this->render_template('selected-filters', [
            'selected_filters' => $selected,
            'has_selected' => $has_selected,
            'atts' => $atts,
        ]);
    }
}
