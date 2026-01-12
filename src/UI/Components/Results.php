<?php
namespace Jankx\SearchEngine\UI\Components;

class Results extends AbstractComponent
{
    protected $name = 'results';

    public function render($atts = [])
    {
        $preset = $atts['preset'] ?? 'default';
        $layout_class = apply_filters('jankx_search_results_layout_class', "jankx-result-preset-{$preset}", $atts);

        // Register action to render initial content
        add_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);

        $content = $this->render_template('results', [
            'atts' => $atts,
            'preset' => $preset,
            'layout_class' => $layout_class,
        ]);

        // Clean up action
        remove_action('jankx_search_results_initial_render', [$this, 'initial_render_posts']);

        return $content;
    }

    public function initial_render_posts($atts)
    {
        $post_types = [];
        $all_post_types = get_post_types(['public' => true], 'names');

        // 1. Check for individual checkboxes (New Checklist UI)
        foreach ($all_post_types as $slug) {
            $key = 'post_type_' . $slug;
            if (isset($atts[$key]) && ($atts[$key] === 'true' || $atts[$key] === true)) {
                $post_types[] = $slug;
            }
        }

        // 2. Fallback to legacy attributes if no checkboxes are checked
        if (empty($post_types)) {
            $legacy = $atts['post_type'] ?? $atts['post_types'] ?? '';
            if (!empty($legacy)) {
                $post_types = is_string($legacy) ? explode(',', $legacy) : $legacy;
            }
        }

        // 3. Default fallback
        if (empty($post_types)) {
            $post_types = 'post';
        }

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
