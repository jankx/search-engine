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

        if (!file_exists($storage . $index_name)) {
            return [
                'results' => [],
                'total' => 0,
                'time' => 0,
            ];
        }

        $this->tnt->selectIndex($index_name);

        $limit = $args['limit'] ?? 10;
        $page = $args['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        // Get up to 500 results to allow for pagination in MVP
        $res = $this->tnt->search($keywords, 500);

        $total = $res['hits'] ?? 0;
        $ids = array_slice($res['ids'] ?? [], $offset, $limit);

        return [
            'results' => $ids,
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
