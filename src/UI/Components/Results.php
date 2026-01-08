<?php
namespace Jankx\SearchEngine\UI\Components;

class Results extends AbstractComponent
{
    protected $name = 'results';

    public function render($atts = [])
    {
        ob_start();
        ?>
        <div class="jankx-search-results-container">
            <!-- Featured Section if enabled -->
            <?php if (!empty($atts['show_featured'])): ?>
                <div class="featured-results row">
                    <!-- Featured items logic -->
                </div>
            <?php endif; ?>

            <!-- Main Results Loop -->
            <div class="search-results <?php echo esc_attr($atts['layout'] ?? 'list'); ?>">
                <div class="results-grid">
                    <!-- Results will be loaded here via AJAX -->
                    <p class="initial-message">Please enter keywords or select filters to see results.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div class="search-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
