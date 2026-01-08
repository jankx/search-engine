<?php
namespace Jankx\SearchEngine\Contracts;

interface SearchResult
{
    public function getHits();

    public function getTotal();

    public function getTimeTaken();

    public function getFacets();
}
