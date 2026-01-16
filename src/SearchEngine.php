<?php
namespace Jankx\SearchEngine;

use Jankx\SearchEngine\Contracts\Engine;
use Jankx\SearchEngine\Engines\TNTSearchEngine;

class SearchEngine
{
    const VERSION = '1.0.9';

    protected static $instance;
    protected $driver;
    protected $config;

    public function __construct($driver = 'tntsearch', array $config = [])
    {
        $this->config = $config;
        $this->driver = $this->resolveDriver($driver);
    }

    public static function getInstance($driver = 'tntsearch', array $config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($driver, $config);
        }
        return self::$instance;
    }

    protected function resolveDriver($driver)
    {
        // Factorize engine creation
        switch ($driver) {
            case 'tntsearch':
                return new TNTSearchEngine($this->config);
            case 'typesense':
            // return new TypesenseEngine($this->config);
            case 'meilisearch':
            // return new MeilisearchEngine($this->config);
            default:
                throw new \Exception("Search Driver [{$driver}] not supported.");
        }
    }

    /**
     * @return Engine
     */
    public function getDriver()
    {
        return $this->driver;
    }

    public function search($keywords, array $args = [])
    {
        return $this->getDriver()->search($keywords, $args);
    }
}
