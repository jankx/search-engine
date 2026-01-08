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
