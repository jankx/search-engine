<?php
namespace Jankx\SearchEngine\Models;

use Jankx\SearchEngine\Contracts\SearchResult;

class SearchResponse implements SearchResult
{
    protected $hits;
    protected $total;
    protected $time;
    protected $facets;

    public function __construct($hits, $total, $time = 0, $facets = [])
    {
        $this->hits = $hits;
        $this->total = $total;
        $this->time = $time;
        $this->facets = $facets;
    }

    public function getHits()
    {
        return $this->hits;
    }
    public function getTotal()
    {
        return $this->total;
    }
    public function getTimeTaken()
    {
        return $this->time;
    }
    public function getFacets()
    {
        return $this->facets;
    }
}
