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
        check_ajax_referer('jankx_search_nonce', 'nonce');

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

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination_html,
        ]);
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

        if (function_exists('flatsome_pagination')) {
            // Flatsome pagination needs a WP_Query object
            $query = new \WP_Query();
            $query->found_posts = $total;
            $query->max_num_pages = $total_pages;
            $query->query_vars['paged'] = $current_page;

            ob_start();
            echo flatsome_pagination($query);
            return ob_get_clean();
        }

        ob_start();
        ?>
        <ul class="page-numbers nav-pagination links text-center">
            <?php if ($current_page > 1): ?>
                <li>
                    <a href="#" class="prev page-number" data-page="<?php echo $current_page - 1; ?>">
                        <i class="icon-angle-left"></i>
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li>
                    <?php if ($i == $current_page): ?>
                        <span aria-current="page" class="page-number current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="#" class="page-number" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <li>
                    <a href="#" class="next page-number" data-page="<?php echo $current_page + 1; ?>">
                        <i class="icon-angle-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        <?php
        return ob_get_clean();
    }
}
