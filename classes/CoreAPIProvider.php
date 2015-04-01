<?php
class CoreAPIProvider extends APIProvider
{
    public function __construct($config)
    {
        parent::__construct($config);

        require_once 'lib/bootstrap-api.php';
    }

    public function getConsumers()
    {
        return RESTAPI\Consumer\Base::findAll();
    }

    public function setConsumer($id)
    {
        $this->consumer_id = $id;

        $consumer = RESTAPI\Consumer\Base::find($id);

        if ($consumer) {
            $this->consumer_key    = $consumer->auth_key;
            $this->consumer_secret = $consumer->auth_secret;
        }
    }

    public function normalizeDiscovery($input)
    {
        $discovery = array();

        foreach ($input as $route => $methods) {
            $discovery[$route] = array();
            foreach ($methods as $method => $description) {
                $discovery[$route][$method] = sprintf('%s, %s - %s', $route, strtoupper($method), $description);
            }
        }

        return $discovery;
    }
}
