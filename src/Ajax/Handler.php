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

        $state = $_POST['state'] ?? [];
        $keywords = $state['q'] ?? '';
        $filters = $state['filters'] ?? [];
        $sort = $state['sort'] ?? 'relevance';

        // Initialize Search Engine (using TNTSearch for MVP)
        $engine = SearchEngine::getInstance('tntsearch', [
            // Config from WP options could go here
        ]);

        $results = $engine->search($keywords, [
            'filters' => $filters,
            'sort' => $sort,
            'limit' => 10,
        ]);

        // For MVP, we'll simulate result rendering
        $html = $this->render_results_html($results);

        wp_send_json_success([
            'html' => $html,
            'pagination' => '', // TODO: Add pagination rendering
        ]);
    }

    protected function render_results_html($results)
    {
        ob_start();
        if (empty($results['results'])) {
            echo '<p class="no-results">No results found for your criteria.</p>';
        } else {
            foreach ($results['results'] as $post_id) {
                // In a real scenario, we'd load post data or use a template
                $post = get_post($post_id);
                if ($post) {
                    ?>
                    <div class="search-result-item">
                        <h3>
                            <?php echo get_the_title($post); ?>
                        </h3>
                        <p>
                            <?php echo get_the_excerpt($post); ?>
                        </p>
                    </div>
                    <?php
                }
            }
        }
        return ob_get_clean();
    }
}
