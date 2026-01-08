<?php
namespace Jankx\SearchEngine\CLI;

use Jankx\SearchEngine\Core\Indexer;
use WP_CLI;

class SearchCommand
{
    /**
     * Rebuild the search index for TNTSearch.
     *
     * ## OPTIONS
     *
     * [--index=<name>]
     * : The name of the index file. Default: resources.index
     *
     * ## EXAMPLES
     *
     *     wp jankx-search rebuild --index=resources.index
     *
     * @when after_wp_load
     */
    public function rebuild($args, $assoc_args)
    {
        $index_name = $assoc_args['index'] ?? 'resources.index';

        WP_CLI::log(sprintf('Starting to rebuild index: %s', $index_name));

        try {
            $indexer = new Indexer();
            $indexer->create_index($index_name);
            WP_CLI::success(sprintf('Index [%s] has been rebuilt successfully.', $index_name));
        } catch (\Exception $e) {
            WP_CLI::error(sprintf('Failed to rebuild index: %s', $e->getMessage()));
        }
    }
}
