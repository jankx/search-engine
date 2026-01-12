<?php
namespace Jankx\SearchEngine\Engines;

class TNTSearchEngine extends AbstractEngine
{
    protected $tnt;

    protected function boot()
    {
        $this->tnt = new \TeamTNT\TNTSearch\TNTSearch();

        $storage = $this->getConfig('storage');
        if (!$storage) {
            $upload_dir = wp_upload_dir();
            $storage = $upload_dir['basedir'] . '/jankx-search';
        }

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

    public function search($keywords, array $args = [])
    {
        $index_name = $this->getConfig('index', 'resources.index');
        $storage = $this->tnt->config['storage'];

        // Check index existence only if we have keywords to search
        $has_index = file_exists($storage . $index_name);
        if (!empty($keywords) && !$has_index) {
            return [
                'results' => [],
                'total' => 0,
                'time' => 0,
            ];
        }

        if ($has_index) {
            $this->tnt->selectIndex($index_name);
        }

        $limit = $args['limit'] ?? 10;
        $page = $args['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        // Get up to 500 results to allow for pagination in MVP
        $ids = [];
        $res = ['execution_time' => 0];
        if (!empty($keywords)) {
            $res = $this->tnt->search($keywords, 500);
            $ids = $res['ids'] ?? [];
        }

        // 1. Build query arguments for WP filtering
        $query_args = [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        // If we have search results, we want to restrict to those and keep order
        if (!empty($ids)) {
            $query_args['post__in'] = $ids;
            $query_args['orderby'] = 'post__in';
        }

        $perform_wp_filter = false;

        // Filter by Post Types
        if (!empty($args['post_types'])) {
            $post_types = is_string($args['post_types']) ? explode(',', $args['post_types']) : $args['post_types'];
            if (!empty($post_types) && !in_array('any', $post_types)) {
                $query_args['post_type'] = $post_types;
                $perform_wp_filter = true;
            }
        } else {
            $query_args['post_type'] = 'any';
        }

        // Filter by Taxonomies (Checkboxes values are now term IDs)
        if (!empty($args['filters'])) {
            $tax_query = [];
            foreach ($args['filters'] as $taxonomy => $terms) {
                if (empty($terms))
                    continue;
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => (array) $terms,
                    'operator' => 'IN',
                ];
            }

            if (!empty($tax_query)) {
                if (count($tax_query) > 1) {
                    $tax_query['relation'] = 'AND';
                }
                $query_args['tax_query'] = $tax_query;
                $perform_wp_filter = true;
            }
        }

        // If keywords are empty, we MUST perform a WP filter to get some results (e.g. current selected category)
        if (empty($keywords)) {
            $perform_wp_filter = true;
            // When keywords are empty, we might want to sort by date by default
            if (!isset($query_args['orderby'])) {
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
            }
        }

        if ($perform_wp_filter) {
            $filtered_ids = get_posts($query_args);
            if (!empty($ids)) {
                // Intersect to keep TNT's order (relevance)
                $ids = array_values(array_intersect($ids, $filtered_ids));
            } else {
                $ids = $filtered_ids;
            }
        }

        $total = count($ids);
        $paged_ids = array_slice($ids, $offset, $limit);

        return [
            'results' => $paged_ids,
            'total' => $total,
            'time' => $res['execution_time'] ?? 0,
        ];
    }

    public function update($document)
    {
        $index_name = $this->getConfig('index', 'resources.index');
        $this->tnt->selectIndex($index_name);
        $indexer = $this->tnt->getIndex();

        $indexer->update($document['id'], $document);
    }

    public function delete($id)
    {
        $index_name = $this->getConfig('index', 'resources.index');
        $this->tnt->selectIndex($index_name);
        $indexer = $this->tnt->getIndex();

        $indexer->delete($id);
    }
}
