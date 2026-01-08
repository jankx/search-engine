<?php
namespace Jankx\SearchEngine\Core;

use TeamTNT\TNTSearch\TNTSearch;

class Indexer
{
    protected $tnt;

    public function __construct()
    {
        $this->tnt = new TNTSearch();

        $upload_dir = wp_upload_dir();
        $storage = $upload_dir['basedir'] . '/jankx-search';

        if (!file_exists($storage)) {
            mkdir($storage, 0755, true);
        }

        $this->tnt->loadConfig([
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'storage' => $storage,
        ]);
    }

    public function create_index($index_name = 'resources.index')
    {
        global $wpdb;
        $indexer = $this->tnt->createIndex($index_name);

        $post_types = apply_filters('jankx_search_index_post_types', array('post', 'featured_item'));
        $taxonomies = apply_filters('jankx_search_index_taxonomies', array('industry', 'thought_leader', 'content_type'));

        $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
        $taxonomies_sql = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";

        // Base query
        $query = "SELECT p.ID as id, p.post_title as title, p.post_content as content,
                  (SELECT GROUP_CONCAT(t.name SEPARATOR ' ') 
                   FROM {$wpdb->terms} t 
                   INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
                   INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
                   WHERE tr.object_id = p.ID 
                   AND tt.taxonomy IN ($taxonomies_sql)) as taxonomies";

        // Allow adding meta keys or other fields via filter
        $query = apply_filters('jankx_search_indexer_select', $query);

        $query .= " FROM {$wpdb->posts} p
                    WHERE p.post_status = 'publish' 
                    AND p.post_type IN ($post_types_sql)";

        $query = apply_filters('jankx_search_indexer_query', $query);

        $indexer->query($query);
        $indexer->run();

        // Update last index time
        update_option('jankx_search_last_rebuild_' . $index_name, time());
    }
}
