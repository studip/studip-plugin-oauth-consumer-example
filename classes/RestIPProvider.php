<?php
class RestIPProvider extends APIProvider
{
    public function getConsumers()
    {
        $query = "SELECT osr_id AS id, osr_application_title AS title
                  FROM oauth_server_registry
                  ORDER BY osr_application_title ASC";
        $statement = DBManager::get()->query($query);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setConsumer($id)
    {
        $this->consumer_id = $id;

        $query = "SELECT osr_consumer_key, osr_consumer_secret
                  FROM oauth_server_registry
                  WHERE osr_id = :id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->consumer_secret = $row['osr_consumer_secret'];
            $this->consumer_key    = $row['osr_consumer_key'];
        }
    }

    public function normalizeDiscovery($input)
    {
        $discovery = array();
        
        foreach ($input['routes'] as $route => $permissions) {
            $discovery[$route] = array();
            foreach ($permissions as $method => $enabled) {
                if (!$enabled) {
                    continue;
                }
                $discovery[$route][$method] = sprintf('%s, %s', $route, strtoupper($method));
            }
        }
        
        return $discovery;
    }
}
