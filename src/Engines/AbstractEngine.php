<?php
namespace Jankx\SearchEngine\Engines;

use Jankx\SearchEngine\Contracts\Engine;

abstract class AbstractEngine implements Engine
{
    protected $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->boot();
    }

    abstract protected function boot();

    public function getConfig($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
