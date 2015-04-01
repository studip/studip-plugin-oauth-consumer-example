<?php
abstract class APIProvider
{
    abstract public function getConsumers();
    abstract public function setConsumer($id);
    abstract public function normalizeDiscovery($input);

    protected $config;
    protected $consumer_id = null;
    protected $consumer_key = null;
    protected $consumer_secret = null;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getConsumerId()
    {
        return $this->consumer_id;
    }

    public function getConfig($key)
    {
        if ($key === 'CONSUMER_KEY') {
            return $this->consumer_key;
        }
        if ($key === 'CONSUMER_SECRET') {
            return $this->consumer_secret;
        }
        return $this->config[$key] ?: false;
    }
}