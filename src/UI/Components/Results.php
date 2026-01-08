<?php
namespace Jankx\SearchEngine\UI\Components;

class Results extends AbstractComponent
{
    protected $name = 'results';

    public function render($atts = [])
    {
        $preset = $atts['preset'] ?? 'default';
        $layout_class = apply_filters('jankx_search_results_layout_class', "jankx-result-preset-{$preset}", $atts);

        ob_start();
        ?>
        <div class="jankx-search-results-container" data-settings="<?php echo esc_attr(json_encode($atts)); ?>">
            <!-- Featured Section if enabled -->
            <?php if (!empty($atts['show_featured'])): ?>
                <div class="featured-results row">
                    <?php do_action('jankx_search_render_featured', $atts); ?>
                </div>
            <?php endif; ?>

            <!-- Main Results Loop -->
            <div class="search-results <?php echo esc_attr($layout_class); ?>">
                <div class="results-grid">
                    <?php
                    // This area is usually populated via AJAX, 
                    // but we provide a hook for initial server-side render if needed
                    do_action('jankx_search_results_initial_render', $atts);
                    ?>
                </div>
            </div>

            <!-- Pagination -->
            <div class="search-pagination">
                <?php do_action('jankx_search_render_pagination', $atts); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
