<?php
namespace Jankx\SearchEngine;

use Jankx\SearchEngine\Integrations\UXBuilder;

class Loader
{
    protected static $instance;

    /**
     * Boot the search engine package
     *
     * @return Loader
     */
    public static function boot()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_integrations();
        $this->init_ui();
        $this->init_ajax();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('jankx-search', \Jankx\SearchEngine\CLI\SearchCommand::class);
        }

        // WP-Cron Support
        add_action('jankx_search_rebuild_index', array($this, 'rebuild_index_cron_job'));
        if (!wp_next_scheduled('jankx_search_rebuild_index')) {
            wp_schedule_event(time(), 'daily', 'jankx_search_rebuild_index');
        }

        add_action('admin_notices', array($this, 'render_rebuild_notice'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('save_post', array($this, 'sync_to_search_engine'), 10, 3);
    }

    /**
     * Display a notice in Admin Dashboard if index needs rebuild
     */
    public function render_rebuild_notice()
    {
        $last_rebuild = get_option('jankx_search_last_rebuild_resources.index');
        if (!$last_rebuild) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php _e('<strong>Jankx Search:</strong> The search index has not been built yet. Please rebuild it to enable search functionality.', 'jankx'); ?>
                    <a href="<?php echo esc_url(add_query_arg('jankx_search_rebuild', '1')); ?>" class="button button-primary"
                        style="margin-left: 10px;">
                        <?php _e('Rebuild Index Now', 'jankx'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        if (isset($_GET['jankx_search_rebuild']) && $_GET['jankx_search_rebuild'] === '1') {
            $this->rebuild_index_cron_job();
            wp_redirect(remove_query_arg('jankx_search_rebuild'));
            exit;
        }
    }

    /**
     * WP-Cron callback to rebuild index
     */
    public function rebuild_index_cron_job()
    {
        try {
            $indexer = new \Jankx\SearchEngine\Core\Indexer();
            $indexer->create_index('resources.index');
        } catch (\Exception $e) {
            error_log('Jankx Search Cron Error: ' . $e->getMessage());
        }
    }

    /**
     * Sync post to search engine index on save
     */
    public function sync_to_search_engine($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') {
            return;
        }

        $allowed_types = apply_filters('jankx_search_index_post_types', array('post', 'featured_item'));
        if (!in_array($post->post_type, $allowed_types)) {
            return;
        }

        $document = apply_filters('jankx_search_sync_document', [
            'id' => $post_id,
            'title' => $post->post_title,
            'content' => strip_tags($post->post_content),
        ], $post);

        $engine = \Jankx\SearchEngine\SearchEngine::getInstance('tntsearch');
        $engine->getDriver()->update($document);
    }

    /**
     * Initialize AJAX handler
     */
    protected function init_ajax()
    {
        $ajax_handler = new \Jankx\SearchEngine\Ajax\Handler();
        $ajax_handler->init();
    }

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'jankx-search-engine',
            content_url('plugins/akselos-customizer/vendor/jankx/search-engine/assets/css/search-engine.css'),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'jankx-search-engine',
            content_url('plugins/akselos-customizer/vendor/jankx/search-engine/assets/js/search-engine.js'),
            array(),
            '1.0.0',
            true
        );

        wp_localize_script('jankx-search-engine', 'jankx_search_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jankx_search_nonce'),
        ));
    }

    /**
     * Initialize third-party integrations (UX Builder, etc.)
     */
    protected function init_integrations()
    {
        // Boot UX Builder if it's Flatsome environment
        $ux_builder = new UXBuilder();
        $ux_builder->init();

        // Boot Gutenberg blocks
        $gutenberg = new Gutenberg();
        $gutenberg->init();
    }

    /**
     * Initialize UI components and assets
     */
    protected function init_ui()
    {
        // Logic for loading CSS/JS or shortcodes can be called here
    }
}
