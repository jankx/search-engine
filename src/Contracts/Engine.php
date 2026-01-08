<?php
namespace Jankx\SearchEngine\Contracts;

interface Engine
{
    /**
     * Search for keywords and filters
     *
     * @param string $keywords
     * @param array $args
     * @return array
     */
    public function search($keywords, array $args = []);

    /**
     * Index a single document
     *
     * @param array $document
     * @return void
     */
    public function update($document);

    /**
     * Remove a document from index
     *
     * @param int|string $id
     * @return void
     */
    public function delete($id);
}
