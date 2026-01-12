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
        ], $atts);

        $preset = $atts['preset'] ?? 'default';
        $layout_class = apply_filters('jankx_search_results_layout_class', "jankx-result-preset-{$preset}", $atts);

        // Register actions to render initial content
        add_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);
        add_action('jankx_search_render_pagination', [$this, 'render_pagination']);

        $content = $this->render_template('results', [
            'atts' => $atts,
            'preset' => $preset,
            'layout_class' => $layout_class,
        ]);

        // Clean up actions
        remove_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);
        remove_action('jankx_search_render_pagination', [$this, 'render_pagination']);

        return $content;
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
            <h3 class="result-title"><a href="<?php echo get_permalink($post); ?>"><?php echo get_the_title($post); ?></a></h3>
            <div class="result-excerpt"><?php echo wp_trim_words(get_the_excerpt($post), 20); ?></div>
        </div>
        <?php
    }
}
