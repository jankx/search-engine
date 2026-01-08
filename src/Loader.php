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

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
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
            array('jquery'),
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
    }

    /**
     * Initialize UI components and assets
     */
    protected function init_ui()
    {
        // Logic for loading CSS/JS or shortcodes can be called here
    }
}
