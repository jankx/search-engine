<?php
namespace Jankx\SearchEngine\Ajax;

use Jankx\SearchEngine\SearchEngine;

class Handler
{
    public function init()
    {
        add_action('wp_ajax_jankx_search_query', array($this, 'handle_search'));
        add_action('wp_ajax_nopriv_jankx_search_query', array($this, 'handle_search'));
    }

    public function handle_search()
    {
        // check_ajax_referer('jankx_search_nonce', 'nonce');
        // Allow public access without strict nonce check for search
        $nonce = $_REQUEST['nonce'] ?? '';
        $is_valid_nonce = wp_verify_nonce($nonce, 'jankx_search_nonce');
        // We log or ignore invalid nonce rather than blocking


        $state_raw = $_POST['state'] ?? '{}';
        $state = json_decode(stripslashes($state_raw), true);

        $keywords = $state['q'] ?? '';
        $filters = $state['filters'] ?? [];
        $sort = $state['sort'] ?? 'relevance';

        // Initialize Search Engine (using TNTSearch for MVP)
        $engine = SearchEngine::getInstance('tntsearch', [
            // Config from WP options could go here
        ]);

        // Resolve Post Types from state (look for post_type_{slug} flags)
        $post_types = [];
        $all_post_types = get_post_types(['public' => true], 'names');
        foreach ($all_post_types as $slug) {
            $key = 'post_type_' . $slug;
            if (isset($state[$key]) && ($state[$key] === 'true' || $state[$key] === true)) {
                $post_types[] = $slug;
            }
        }
        if (empty($post_types)) {
            $legacy = $state['post_type'] ?? $state['post_types'] ?? '';
            if (!empty($legacy)) {
                $post_types = is_string($legacy) ? explode(',', $legacy) : $legacy;
            }
        }
        
        // Critical Fix: Ensure all Resource CPTs are included in the search scope.
        // If sorting/filtering by Resource taxonomies, we must search 'any' post type to catch White Papers, Webinars, etc.
        // Otherwise, default settings might restrict search to just 'post', causing 0 counts for CPTs.
        if (isset($filters['featured_item_category']) || isset($filters['industry']) || isset($state['filters']['featured_item_category'])) {
            $post_types = []; // Empty array triggers 'any' in TNTSearchEngine
        }

        $limit = isset($state['limit']) ? (int) $state['limit'] : 10;
        $results = $engine->search($keywords, [
            'filters' => $filters,
            'sort' => $sort,
            'limit' => $limit,
            'page' => $state['page'] ?? 1,
            'post_types' => $post_types,
        ]);

        // Render results using the current state (includes presets)
        $html = $this->render_results_html($results, $state);

        $show_pagination = $state['show_pagination'] ?? 'true';
        $pagination_html = '';
        if ($show_pagination === 'true' || $show_pagination === true) {
            $pagination_html = $this->render_pagination_html($results['total'], $limit, $state['page'] ?? 1, $state);
        }

        $selected_filters_html = (new \Jankx\SearchEngine\UI\Components\SelectedFilters())->render([
            'filters' => $filters,
            'keyword' => $keywords
        ]);

        // --- Term Counts Calculation ---
        // --- Term Counts Calculation ---
        $all_ids = $results['all_ids'] ?? [];
        // 1. Base counts (using current intersection)
        $term_counts = $this->get_term_counts($all_ids);

        // 2. Shadow Search for Term Counts (Dependency Logic)
        // We group 'Content Type' taxes together so that selecting one doesn't zero out counts for others in the same group.
        $or_groups = [
            'content_type_group' => ['category', 'featured_item_category', 'featured_item_tag'], // These share the "Content Type" UI block
        ];

        // Identify which "Logical Groups" are active
        $active_logical_groups = [];
        if (!empty($filters)) {
            foreach (array_keys($filters) as $tax) {
                $found_group = false;
                foreach ($or_groups as $group_key => $group_taxes) {
                    if (in_array($tax, $group_taxes)) {
                        $active_logical_groups[$group_key] = $group_taxes;
                        $found_group = true;
                        break;
                    }
                }
                if (!$found_group) {
                    // This taxonomy is its own group (e.g. industry)
                    $active_logical_groups[$tax] = [$tax];
                }
            }
        }

        if (!empty($active_logical_groups)) {
            foreach ($active_logical_groups as $group_key => $taxes_in_group) {
                // Prepare Shadow Context: Remove ALL filters belonging to this active group
                $shadow_filters = $filters;
                foreach ($taxes_in_group as $tax) {
                    unset($shadow_filters[$tax]);
                }

                // Perform Shadow Search (Filtered by OTHER groups only)
                $shadow_results = $engine->search($keywords, [
                    'filters' => $shadow_filters,
                    'sort' => $sort,
                    'limit' => 1000, 
                    'page' => 1,
                    'post_types' => $post_types,
                ]);
                
                $shadow_ids = $shadow_results['all_ids'] ?? [];
                
                // Update counts for ALL taxonomies in this group
                // This ensures we see "sibling" counts for everything in the group (e.g. White Papers count when Video is selected)
                foreach ($taxes_in_group as $tax) {
                    $shadow_counts = $this->get_term_counts($shadow_ids, $tax);
                    foreach ($shadow_counts as $term_id => $count) {
                        $term_counts[$term_id] = $count;
                    }
                }
            }
        }

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination_html,
            'selected_filters' => $selected_filters_html,
            'term_counts' => $term_counts,
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

    public function render_results_html($results, $state = [])
    {
        $preset = $state['preset'] ?? 'default';
        ob_start();

        if (empty($results['results'])) {
            echo apply_filters('jankx_search_no_results_html', '<p class="no-results">No results found for your criteria.</p>', $state);
        } else {
            foreach ($results['results'] as $post_id) {
                $post = get_post($post_id);
                if ($post) {
                    // Allow theme/plugin to override the entire item HTML
                    $item_html = apply_filters('jankx_search_result_item_html', '', $post, $preset, $state);

                    if (!empty($item_html)) {
                        echo $item_html;
                    } else {
                        $this->render_default_item($post, $preset, $state);
                    }
                }
            }
        }
        return ob_get_clean();
    }

    protected function render_default_item($post, $preset, $state = [])
    {
        $video_url = get_post_meta($post->ID, '_video_embed_url', true) ?: get_post_meta($post->ID, '_video_url', true);
        $is_video = (get_post_format($post) === 'video' || $post->post_type === 'webinar') && !empty($video_url);
        $use_popup = (isset($state['enable_video_popup']) && ($state['enable_video_popup'] === 'true' || $state['enable_video_popup'] === true)) && $is_video;

        $link = $use_popup ? $video_url : get_permalink($post);
        $class = $use_popup ? 'open-video' : '';
        ?>
        <div class="result-item">
            <?php if (has_post_thumbnail($post)): ?>
                <div class="result-image">
                    <a href="<?php echo esc_url($link); ?>" class="<?php echo esc_attr($class); ?>">
                        <?php echo get_the_post_thumbnail($post, 'medium'); ?>
                        <?php if ($is_video): ?>
                            <div class="video-overlay-icon">
                                <i class="dashicons dashicons-controls-play"></i>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>
            <div class="result-content">
                <span class="result-label"><?php echo esc_html(strtoupper($this->get_post_label($post))); ?></span>
                <h3 class="result-title">
                    <a href="<?php echo esc_url($link); ?>"
                        class="<?php echo esc_attr($class); ?>"><?php echo get_the_title($post); ?></a>
                </h3>
                <div class="result-excerpt">
                    <?php echo wp_trim_words(get_the_excerpt($post), 25); ?>
                </div>
            </div>
        </div>
        <?php
    }

    protected function get_post_label($post)
    {
        $post_type = get_post_type($post);
        $taxonomies = get_object_taxonomies($post_type, 'names');

        // Priority taxonomies
        $priority_taxonomies = ['category', 'product_cat', 'featured_item_category', 'topic'];
        $best_tax = '';
        foreach ($priority_taxonomies as $tax) {
            if (in_array($tax, $taxonomies)) {
                $best_tax = $tax;
                break;
            }
        }

        // If no priority tax found, look for something ending in _category or _cat
        if (!$best_tax) {
            foreach ($taxonomies as $tax) {
                if (str_ends_with($tax, '_category') || str_ends_with($tax, '_cat')) {
                    $best_tax = $tax;
                    break;
                }
            }
        }

        if (!$best_tax && !empty($taxonomies)) {
            $best_tax = $taxonomies[0];
        }

        if ($best_tax) {
            $terms = get_the_terms($post, $best_tax);
            if (!empty($terms) && !is_wp_error($terms)) {
                return $terms[0]->name;
            }
        }

        return get_post_type_object($post_type)->labels->singular_name;
    }

    public function render_pagination_html($total, $limit, $current_page, $state = [])
    {
        $total_pages = ceil($total / $limit);
        if ($total_pages <= 1)
            return '';

        // Build base URL with current args
        $base_url = get_permalink(1296); // Resources Page ID
        $query_args = [];
        
        if (!empty($state['q'])) {
            $query_args['q'] = $state['q'];
        }
        if (!empty($state['sort'])) {
            $query_args['sort'] = $state['sort'];
        }
        
        if (!empty($state['filters'])) {
            foreach ($state['filters'] as $tax => $terms) {
                if (!empty($terms)) {
                    $query_args[$tax] = $terms;
                }
            }
        }
        
        $base_url = add_query_arg($query_args, $base_url);
        
        // Generate links using WP core function
        $links = paginate_links([
            'base'      => add_query_arg('page', '%#%', $base_url),
            'format'    => '',
            'total'     => $total_pages,
            'current'   => $current_page,
            'type'      => 'array',
            'prev_text' => '<i class="icon-angle-left"></i>',
            'next_text' => '<i class="icon-angle-right"></i>',
        ]);
        
        if (empty($links)) return '';

        $output = '<ul class="page-numbers nav-pagination links text-center">';
        foreach ($links as $link) {
            // Extract page number from href to add data-page attribute for JS
            // First check for query param format (?page=2 or &page=2)
            if (preg_match('/[?&]page=(\d+)/', $link, $matches)) {
                $p = $matches[1];
                $link = str_replace('<a ', '<a data-page="' . esc_attr($p) . '" ', $link);
            } 
            // Fallback check for permalink format (/page/2) although base usually enforces query arg
            elseif (preg_match('/\/page\/(\d+)/', $link, $matches)) {
                $p = $matches[1];
                $link = str_replace('<a ', '<a data-page="' . esc_attr($p) . '" ', $link);
            }
            
            // Adjust classes for Flatsome compatibility (page-numbers -> page-number)
            $link = str_replace('page-numbers', 'page-number', $link);
            $output .= '<li>' . $link . '</li>';
        }
        $output .= '</ul>';
        
        return $output;
    }
}
