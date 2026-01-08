<?php
namespace Jankx\SearchEngine\Integrations;

use Jankx\SearchEngine\UI\Components\Keyword;
use Jankx\SearchEngine\UI\Components\Filter;
use Jankx\SearchEngine\UI\Components\Sorter;
use Jankx\SearchEngine\UI\Components\Results;

class Gutenberg
{
    public function init()
    {
        add_action('init', array($this, 'register_blocks'));
        add_action('init', array($this, 'register_patterns'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_assets'));
    }

    public function register_patterns()
    {
        if (function_exists('register_block_pattern')) {
            register_block_pattern(
                'jankx/search-hub-layout',
                array(
                    'title' => __('Search Hub Layout (Akselos)', 'jankx'),
                    'description' => _x('A complete search hub with sidebar filters and results grid.', 'Block pattern description', 'jankx'),
                    'categories' => array('header'),
                    'content' => '<!-- wp:columns {"className":"jankx-search-hub"} -->
                                    <div class="wp-block-columns jankx-search-hub"><!-- wp:column {"width":"30%"} -->
                                    <div class="wp-block-column" style="flex-basis:30%"><!-- wp:jankx/search-filter {"title":"Content Type","taxonomy":"content_type"} /-->
                                    <!-- wp:jankx/search-filter {"title":"Industries","taxonomy":"industry"} /-->
                                    <!-- wp:jankx/search-filter {"title":"Authors","taxonomy":"thought_leader"} /--></div>
                                    <!-- /wp:column -->

                                    <!-- wp:column {"width":"70%"} -->
                                    <div class="wp-block-column" style="flex-basis:70%"><!-- wp:jankx/search-keyword /-->
                                    <!-- wp:jankx/search-sorter /-->
                                    <!-- wp:jankx/search-results {"preset":"akselos"} /--></div>
                                    <!-- /wp:column --></div>
                                    <!-- /wp:columns -->',
                )
            );
        }
    }

    public function register_blocks()
    {
        // Keyword Block
        register_block_type('jankx/search-keyword', array(
            'render_callback' => array($this, 'render_keyword'),
            'attributes' => array(
                'placeholder' => array('type' => 'string', 'default' => 'Search...'),
            ),
        ));

        // Filter Block
        register_block_type('jankx/search-filter', array(
            'render_callback' => array($this, 'render_filter'),
            'attributes' => array(
                'title' => array('type' => 'string'),
                'taxonomy' => array('type' => 'string', 'default' => 'industry'),
            ),
        ));

        // Sorter Block
        register_block_type('jankx/search-sorter', array(
            'render_callback' => array($this, 'render_sorter'),
            'attributes' => array(
                'label' => array('type' => 'string', 'default' => 'Sort by'),
            ),
        ));

        // Results Block
        register_block_type('jankx/search-results', array(
            'render_callback' => array($this, 'render_results'),
            'attributes' => array(
                'show_featured' => array('type' => 'boolean', 'default' => true),
                'preset' => array('type' => 'string', 'default' => 'default'),
            ),
        ));
    }

    public function render_keyword($attributes)
    {
        return (new Keyword())->render($attributes);
    }

    public function render_filter($attributes)
    {
        return (new Filter())->render($attributes);
    }

    public function render_sorter($attributes)
    {
        return (new Sorter())->render($attributes);
    }

    public function render_results($attributes)
    {
        return (new Results())->render($attributes);
    }

    public function enqueue_block_assets()
    {
        wp_enqueue_script(
            'jankx-search-gutenberg',
            content_url('plugins/akselos-customizer/vendor/jankx/search-engine/assets/js/gutenberg.js'),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            '1.0.0',
            true
        );
    }
}
