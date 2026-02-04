<?php
namespace Jankx\SearchEngine\UI\Components;

class Results extends AbstractComponent
{
    protected $name = 'results';

    public function render($atts = [])
    {
        $atts = array_merge([
            'post_type_post' => 'true', // Default post type
            'show_pagination' => 'true', // Default pagination visibility
            'show_featured' => 'true', // Default show featured
            'enable_video_popup' => 'true', // Default enable video popup
        ], $atts);

        $preset = $atts['preset'] ?? 'default';
        $layout_class = apply_filters('jankx_search_results_layout_class', "jankx-result-preset-{$preset}", $atts);

        // Register actions to render initial content
        add_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);
        add_action('jankx_search_render_pagination', [$this, 'render_pagination']);
        add_action('jankx_search_render_featured', [$this, 'render_featured_results']);

        $content = $this->render_template('results', [
            'atts' => $atts,
            'preset' => $preset,
            'layout_class' => $layout_class,
        ]);

        // Clean up actions
        remove_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);
        remove_action('jankx_search_render_pagination', [$this, 'render_pagination']);
        remove_action('jankx_search_render_featured', [$this, 'render_featured_results']);

        return $content;
    }

    public function render_featured_results($atts)
    {
        if (!isset($atts['show_featured']) || ($atts['show_featured'] === 'false' || $atts['show_featured'] === false)) {
            return;
        }

        $post_types = $this->resolve_post_types($atts);
        $args = [
            'post_type' => $post_types,
            'posts_per_page' => 2,
            'meta_query' => [
                [
                    'key' => '_is_featured',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            echo '<div class="jankx-featured-items">';
            while ($query->have_posts()) {
                $query->the_post();
                $post = get_post();
                $video_url = get_post_meta($post->ID, '_video_embed_url', true) ?: get_post_meta($post->ID, '_video_url', true);
                $is_video = !empty($video_url);
                $enable_setting = $atts['enable_video_popup'] ?? 'true';
                $use_popup = ($enable_setting === 'true' || $enable_setting === true) && $is_video;
                $link = $use_popup ? $video_url : get_permalink();
                $class = $use_popup ? 'open-video' : '';
                ?>
                <div class="featured-item">
                    <div class="result-image">
                        <div class="featured-badge">
                            <i class="icon-star"></i> Featured
                        </div>
                        <?php if (has_post_thumbnail()): ?>
                            <a href="<?php echo esc_url($link); ?>" class="<?php echo esc_attr($class); ?>">
                                <?php the_post_thumbnail('large'); ?>
                                <?php if ($is_video): ?>
                                    <div class="video-overlay-icon">
                                        <i class="dashicons dashicons-controls-play"></i>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="result-content">
                        <span class="result-label"><?php echo esc_html(strtoupper($this->get_post_label($post))); ?></span>
                        <h3 class="result-title">
                            <a href="<?php echo esc_url($link); ?>" class="<?php echo esc_attr($class); ?>"><?php the_title(); ?></a>
                        </h3>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
            wp_reset_postdata();
        }
    }

    public function initial_render_posts($atts)
    {
        $post_types = $this->resolve_post_types($atts);

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        if (isset($_GET['page'])) {
            $paged = max(1, (int) $_GET['page']);
        }

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $atts['limit'] ?? 10,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($_GET['q'])) {
            $args['s'] = sanitize_text_field($_GET['q']);
        }

        if (!empty($_GET['sort'])) {
            switch ($_GET['sort']) {
                case 'date_asc':
                    $args['orderby'] = 'date';
                    $args['order'] = 'ASC';
                    break;
                case 'title_asc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'ASC';
                    break;
                case 'title_desc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'DESC';
                    break;
                case 'relevance':
                    if (!empty($args['s'])) {
                        $args['orderby'] = 'relevance';
                        unset($args['order']);
                    }
                    break;
            }
        }

        // Apply filters from URL to initial render
        $filters = $_GET['filter'] ?? [];
        if (empty($filters)) {
            // Support simplified URL params if not using 'filter' array
            $taxonomies = get_taxonomies(['public' => true]);
            foreach ($taxonomies as $tax) {
                if (isset($_GET[$tax])) {
                    $filters[$tax] = (array) $_GET[$tax];
                }
            }
        }

        if (!empty($filters)) {
            $tax_query = ['relation' => 'AND'];
            foreach ($filters as $taxonomy => $terms) {
                if (!empty($terms)) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $terms,
                        'operator' => 'IN'
                    ];
                }
            }
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post = get_post();
                // Use the same filter as Handler to ensure consistency (item html)
                $item_html = apply_filters('jankx_search_result_item_html', '', $post, $atts['preset'] ?? 'default', $atts);

                if (!empty($item_html)) {
                    echo $item_html;
                } else {
                    $this->render_default_item($post, $atts);
                }
            }
            wp_reset_postdata();
        } else {
            echo '<p class="no-results">' . __('No posts found.', 'jankx') . '</p>';
        }
    }

    public function render_pagination($atts)
    {
        if (isset($atts['show_pagination']) && ($atts['show_pagination'] === 'false' || $atts['show_pagination'] === false)) {
            return;
        }

        // We need total posts for the initial render's pagination
        $post_types = $this->resolve_post_types($atts);
        
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        if (isset($_GET['page'])) {
            $paged = max(1, (int) $_GET['page']);
        }

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $atts['limit'] ?? 10,
            'paged' => $paged,
            'fields' => 'ids',
        ];

        if (!empty($_GET['q'])) {
            $args['s'] = sanitize_text_field($_GET['q']);
        }

        // Apply filters from URL to pagination query too
        $filters = $_GET['filter'] ?? [];
        if (empty($filters)) {
            // Support simplified URL params
            $taxonomies = get_taxonomies(['public' => true]);
            foreach ($taxonomies as $tax) {
                if (isset($_GET[$tax])) {
                    $filters[$tax] = (array) $_GET[$tax];
                }
            }
        }

        if (!empty($filters)) {
            $tax_query = ['relation' => 'AND'];
            foreach ($filters as $taxonomy => $terms) {
                if (!empty($terms)) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $terms,
                        'operator' => 'IN'
                    ];
                }
            }
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }

        $query = new \WP_Query($args);

        if (function_exists('flatsome_pagination')) {
            echo flatsome_pagination($query);
        } else {
            $handler = new \Jankx\SearchEngine\Ajax\Handler();
            // Pass current state to render_pagination_html to generate corect links
            $state = [
                'q' => $_GET['q'] ?? '',
                'sort' => $_GET['sort'] ?? '',
                'filters' => $filters,
            ];
            echo $handler->render_pagination_html($query->found_posts, $atts['limit'] ?? 10, $paged, $state);
        }
    }

    protected function resolve_post_types($atts)
    {
        $post_types = [];
        $all_post_types = get_post_types(['public' => true], 'names');
        foreach ($all_post_types as $slug) {
            if (isset($atts['post_type_' . $slug]) && ($atts['post_type_' . $slug] === 'true' || $atts['post_type_' . $slug] === true)) {
                $post_types[] = $slug;
            }
        }
        if (empty($post_types)) {
            $legacy = $atts['post_type'] ?? $atts['post_types'] ?? '';
            $post_types = !empty($legacy) ? (is_string($legacy) ? explode(',', $legacy) : $legacy) : 'post';
        }
        return $post_types;
    }

    protected function render_default_item($post, $atts = [])
    {
        $video_url = get_post_meta($post->ID, '_video_embed_url', true) ?: get_post_meta($post->ID, '_video_url', true);
        $is_video = !empty($video_url);
        $enable_setting = $atts['enable_video_popup'] ?? 'true';
        $use_popup = ($enable_setting === 'true' || $enable_setting === true) && $is_video;
        $link = $use_popup ? $video_url : get_permalink($post);
        $class = $use_popup ? 'open-video' : '';
        // Simple fallback default item if no filter hooks it
        ?>
        <div class="result-item default-fallback">
            <div class="result-image">
                <?php if (has_post_thumbnail($post)): ?>
                    <a href="<?php echo esc_url($link); ?>"
                        class="<?php echo esc_attr($class); ?>"><?php echo get_the_post_thumbnail($post, 'medium'); ?>
                        <?php if ($is_video): ?>
                            <div class="video-overlay-icon">
                                <i class="dashicons dashicons-controls-play"></i>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="result-content">
                <span class="result-label"><?php echo esc_html(strtoupper($this->get_post_label($post))); ?></span>
                <h3 class="result-title"><a href="<?php echo esc_url($link); ?>"
                        class="<?php echo esc_attr($class); ?>"><?php echo get_the_title($post); ?></a>
                </h3>
                <div class="result-excerpt"><?php echo wp_trim_words(get_the_excerpt($post), 20); ?></div>
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
}
