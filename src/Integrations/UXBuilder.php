<?php
namespace Jankx\SearchEngine\Integrations;

class UXBuilder
{
    public function init()
    {
        add_action('ux_builder_setup', array($this, 'register_elements'));
    }

    public function register_elements()
    {
        add_ux_builder_shortcode('jankx_search_hub', array(
            'name' => __('Search Hub', 'jankx'),
            'category' => __('Jankx', 'jankx'),
            'priority' => 10,
            'options' => array(
                'post_types' => array(
                    'type' => 'select',
                    'heading' => 'Post Types',
                    'multiple' => true,
                    'options' => array(
                        'post' => 'Posts',
                        'featured_item' => 'Featured Items',
                    ),
                ),
                'taxonomies' => array(
                    'type' => 'select',
                    'heading' => 'Filters (Taxonomies)',
                    'multiple' => true,
                    'options' => array(
                        'industry' => 'Industries',
                        'thought_leader' => 'Authors/Leaders',
                        'content_type' => 'Content Types',
                    ),
                ),
            ),
        ));
    }
}
