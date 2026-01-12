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
                ?>
                <div class="featured-item">
                    <div class="result-image">
                        <div class="featured-badge">
                            <i class="icon-star"></i> Featured
                        </div>
                        <?php if (has_post_thumbnail()): ?>
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large'); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="result-content">
                        <span class="result-label"><?php echo esc_html(strtoupper($this->get_post_label($post))); ?></span>
                        <h3 class="result-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $atts['limit'] ?? 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

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
                    $this->render_default_item($post);
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
        // This is a bit redundant but necessary for the first page load
        $post_types = $this->resolve_post_types($atts);
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $atts['limit'] ?? 10,
            'fields' => 'ids',
        ];
        $query = new \WP_Query($args);

        if (function_exists('flatsome_pagination')) {
            echo flatsome_pagination($query);
        } else {
            $handler = new \Jankx\SearchEngine\Ajax\Handler();
            echo $handler->render_pagination_html($query->found_posts, $atts['limit'] ?? 10, 1, $atts);
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

    protected function render_default_item($post)
    {
        // Simple fallback default item if no filter hooks it
        ?>
        <div class="result-item default-fallback">
            <div class="result-image">
                <?php if (has_post_thumbnail($post)): ?>
                    <a href="<?php echo get_permalink($post); ?>"><?php echo get_the_post_thumbnail($post, 'medium'); ?></a>
                <?php endif; ?>
            </div>
            <div class="result-content">
                <span class="result-label"><?php echo esc_html(strtoupper($this->get_post_label($post))); ?></span>
                <h3 class="result-title"><a href="<?php echo get_permalink($post); ?>"><?php echo get_the_title($post); ?></a>
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
        $priority_taxonomies = ['category', 'product_cat', 'topic'];
        $best_tax = '';
        foreach ($priority_taxonomies as $tax) {
            if (in_array($tax, $taxonomies)) {
                $best_tax = $tax;
                break;
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
