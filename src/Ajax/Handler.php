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

        $results = $engine->search($keywords, [
            'filters' => $filters,
            'sort' => $sort,
            'limit' => 10,
            'page' => $state['page'] ?? 1,
            'post_types' => $post_types,
        ]);

        // Render results using the current state (includes presets)
        $html = $this->render_results_html($results, $state);
        $pagination_html = $this->render_pagination_html($results['total'], 10, $state['page'] ?? 1);

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination_html,
        ]);
    }

    protected function render_results_html($results, $state = [])
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
                        $this->render_default_item($post, $preset);
                    }
                }
            }
        }
        return ob_get_clean();
    }

    protected function render_default_item($post, $preset)
    {
        ?>
        <div class="result-item">
            <?php if (has_post_thumbnail($post)): ?>
                <div class="result-image">
                    <?php echo get_the_post_thumbnail($post, 'medium'); ?>
                </div>
            <?php endif; ?>
            <div class="result-content">
                <span class="result-label"><?php echo get_post_type($post); ?></span>
                <h3 class="result-title">
                    <a href="<?php echo get_permalink($post); ?>"><?php echo get_the_title($post); ?></a>
                </h3>
                <div class="result-excerpt">
                    <?php echo wp_trim_words(get_the_excerpt($post), 25); ?>
                </div>
            </div>
        </div>
        <?php
    }

    protected function render_pagination_html($total, $limit, $current_page)
    {
        $total_pages = ceil($total / $limit);
        if ($total_pages <= 1)
            return '';

        ob_start();
        ?>
        <nav class="pagination-nav">
            <ul class="pagination-list">
                <?php if ($current_page > 1): ?>
                    <li><a href="#" class="page-numbers" data-page="<?php echo $current_page - 1; ?>">&laquo; Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li>
                        <a href="#" class="page-numbers <?php echo ($i == $current_page) ? 'current' : ''; ?>"
                            data-page="<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <li><a href="#" class="page-numbers" data-page="<?php echo $current_page + 1; ?>">Next &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php
        return ob_get_clean();
    }
}
