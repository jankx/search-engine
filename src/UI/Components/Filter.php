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

        // Resolve Active Filters
        $active_filters = isset($_GET['filter']) ? $_GET['filter'] : [];
        if (empty($active_filters)) {
            foreach ($enabledTax as $key => $value) {
                $tax = str_replace('show_tax_', '', $key);
                if (isset($_GET[$tax])) {
                    $active_filters[$tax] = (array) $_GET[$tax];
                }
            }
        }

        // Resolve Post Types
        $post_types = [];
        foreach ($atts as $k => $v) {
            if (strpos($k, 'post_type_') === 0 && $v === 'true') {
                $post_types[] = str_replace('post_type_', '', $k);
            }
        }

        // Calculate Contextual Counts
        $term_counts = [];
        $engine = \Jankx\SearchEngine\SearchEngine::getInstance('tntsearch');
        $keyword = $_GET['q'] ?? '';

        // 1. Base Search (Intersection)
        $base_results = $engine->search($keyword, [
            'filters' => $active_filters,
            'limit' => 1,
            'post_types' => $post_types,
        ]);
        $all_ids = $base_results['all_ids'] ?? [];
        $term_counts = $this->get_term_counts($all_ids);

        // 2. Shadow Search (OR logic for active taxonomies)
        if (!empty($active_filters)) {
            foreach (array_keys($active_filters) as $active_tax) {
                $shadow_filters = $active_filters;
                unset($shadow_filters[$active_tax]);

                $shadow_results = $engine->search($keyword, [
                    'filters' => $shadow_filters,
                    'limit' => 1,
                    'post_types' => $post_types,
                ]);
                $shadow_ids = $shadow_results['all_ids'] ?? [];
                $shadow_counts = $this->get_term_counts($shadow_ids, $active_tax);

                foreach ($shadow_counts as $tid => $cnt) {
                    $term_counts[$tid] = $cnt;
                }
            }
        }

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

            $terms = get_terms($tax_args);
            // Update terms with contextual counts
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term->count = $term_counts[$term->term_id] ?? 0;
                }
            }

            $tax_data[$tax] = [
                'tax_obj' => get_taxonomy($tax),
                'terms' => $terms
            ];
            $hasItems = true;
        }

        return $this->render_template('filters', [
            'tax_data' => apply_filters('jankx/search-engine/filter/datas', $tax_data, $this),
            'atts' => $atts,
            'has_items' => $hasItems,
            'active_filters' => $active_filters,
        ]);
    }

    protected function get_term_counts($post_ids, $taxonomy = null)
    {
        global $wpdb;
        if (empty($post_ids)) {
            return [];
        }

        // Sanitize IDs (ensure integers)
        $post_ids = array_map('intval', $post_ids);
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        
        $sql = "
            SELECT tt.term_id, COUNT(DISTINCT tr.object_id) as count
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id IN ($placeholders)
        ";
        
        $args = $post_ids;

        if ($taxonomy) {
            $sql .= " AND tt.taxonomy = %s";
            $args[] = $taxonomy;
        }

        $sql .= " GROUP BY tt.term_id";

        $results = $wpdb->get_results($wpdb->prepare($sql, $args));

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->term_id] = (int) $row->count;
        }
        return $counts;
    }
}
