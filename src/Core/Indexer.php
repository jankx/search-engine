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
        $indexer = $this->tnt->createIndex($index_name);

        // Advanced query to include taxonomies for better searching
        $query = "SELECT p.ID as id, p.post_title as title, p.post_content as content,
                  (SELECT GROUP_CONCAT(t.name SEPARATOR ' ') 
                   FROM wp_terms t 
                   INNER JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id 
                   INNER JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
                   WHERE tr.object_id = p.ID 
                   AND tt.taxonomy IN ('industry', 'thought_leader', 'content_type')) as taxonomies
                  FROM wp_posts p
                  WHERE p.post_status = 'publish' 
                  AND p.post_type IN ('post', 'featured_item')";

        $indexer->query($query);
        $indexer->run();

        // Update last index time
        update_option('jankx_search_last_rebuild_' . $index_name, time());
    }
}
