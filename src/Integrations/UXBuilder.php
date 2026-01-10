<?php
namespace Jankx\SearchEngine\Integrations;

class UXBuilder
{
    public function init()
    {
        add_action('ux_builder_setup', array($this, 'register_elements'));
        add_action('init', array($this, 'register_shortcodes'));
    }

    public function register_shortcodes()
    {
        add_shortcode('jankx_search_keyword', array($this, 'render_keyword'));
        add_shortcode('jankx_search_filter', array($this, 'render_filter'));
        add_shortcode('jankx_search_sorter', array($this, 'render_sorter'));
        add_shortcode('jankx_search_results', array($this, 'render_results'));
    }

    public function register_elements()
    {
        $category = __('Jankx Search', 'jankx');

        $scripts = array('jankx-search-engine');
        $styles = array('jankx-search-engine');

        // 1. Keyword Element
        add_ux_builder_shortcode('jankx_search_keyword', array(
            'name' => __('Search Keyword', 'jankx'),
            'category' => $category,
            'scripts' => $scripts,
            'styles' => $styles,
            'options' => array(
                'placeholder' => array(
                    'type' => 'textfield',
                    'heading' => 'Placeholder',
                    'default' => 'Search...',
                ),
            ),
        ));

        // 2. Filter Element
        add_ux_builder_shortcode('jankx_search_filter', array(
            'name' => __('Search Filter', 'jankx'),
            'category' => $category,
            'scripts' => $scripts,
            'styles' => $styles,
            'options' => array(
                'title' => array(
                    'type' => 'textfield',
                    'heading' => 'Filter Title',
                ),
                'taxonomy' => array(
                    'type' => 'select',
                    'heading' => 'Taxonomy',
                    'options' => array(
                        'thought_leader' => 'Authors',
                        'industry' => 'Industries',
                    ),
                ),
            ),
        ));

        // 3. Sorter Element
        add_ux_builder_shortcode('jankx_search_sorter', array(
            'name' => __('Search Sorter', 'jankx'),
            'category' => $category,
            'scripts' => $scripts,
            'styles' => $styles,
            'options' => array(
                'label' => array(
                    'type' => 'textfield',
                    'heading' => 'Label',
                    'default' => 'Sort by',
                ),
            ),
        ));

        // 4. Results Element
        add_ux_builder_shortcode('jankx_search_results', array(
            'name' => __('Search Results', 'jankx'),
            'category' => $category,
            'scripts' => $scripts,
            'styles' => $styles,
            'options' => array(
                'show_featured' => array(
                    'type' => 'checkbox',
                    'heading' => 'Show Featured Items',
                    'default' => 'true',
                ),
                'preset' => array(
                    'type' => 'select',
                    'heading' => 'UI Preset',
                    'default' => 'default',
                    'options' => array(
                        'default' => 'List View (Premium)',
                        'grid' => 'Grid/Card View',
                        'akselos' => 'Akselos Official UI',
                        'custom' => 'Theme Custom Pattern',
                    ),
                ),
                'layout' => array(
                    'type' => 'select',
                    'heading' => 'Layout',
                    'options' => array(
                        'list' => 'List View',
                        'grid' => 'Grid View',
                    ),
                ),
            ),
        ));
    }

    public function render_keyword($atts)
    {
        return (new \Jankx\SearchEngine\UI\Components\Keyword())->render($atts);
    }
    public function render_filter($atts)
    {
        return (new \Jankx\SearchEngine\UI\Components\Filter())->render($atts);
    }
    public function render_sorter($atts)
    {
        return (new \Jankx\SearchEngine\UI\Components\Sorter())->render($atts);
    }
    public function render_results($atts)
    {
        return (new \Jankx\SearchEngine\UI\Components\Results())->render($atts);
    }
}
