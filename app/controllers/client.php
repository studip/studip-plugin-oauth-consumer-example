<?php
require_once 'lib/classes/UserConfig.class.php';

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__FILE__) . '/../../vendor'));
spl_autoload_register(function ($name) {
    $file = str_replace(array('\\', '_'), '/', $name);
    $file = '/vendor/' . $file . '.php';
    $path = realpath(dirname(__FILE__) . '/../..');
    include_once $path . $file;
}, false, true);

class ClientController extends StudipController
{
    public $content_types = array(
        'application/json',
        'text/php',
        'text/xml',
    );

    const CACHE_KEY_REQUEST_TOKEN = '/oauth/request_token/';
    const CONFIG_KEY_ACCESS_TOKEN = 'OAUTH_CLIENT_ACCESS_TOKEN';

    function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        Navigation::activateItem('/oauth_consumer');
        PageLayout::setTitle(_('OAuth Client'));

        # make container even more accessible
        $this->container = $this->dispatcher->container;
        
        $this->config = UserConfig::get($GLOBALS['user']->id);
        $this->cache  = StudipCacheFactory::getCache();
        
        URLHelper::removeLinkParam('cid');
    }

    function index_action()
    {
        $parameters = Request::getArray('parameters');
        if (!empty($parameters) and !empty($parameters['name']) and !empty($parameters['value'])) {
            foreach ($parameters['name'] as $index => $name) {
                if (empty($name)) {
                    unset($parameters['name'][$index], $parameters['value'][$index]);
                }
            }
            $parameters = empty($parameters['name'])
                        ? array()
                        : array_combine($parameters['name'], $parameters['value']);
        }

        $resource = Request::get('resource');
        if ($resource) {
            try {
                $this->result = $this->request($resource, $parameters, Request::get('content_type'),
                                               Request::option('method'), Request::optionArray('signed'),
                                               !Request::int('consume'));
            } catch (Exception $e) {
                $details = array(nl2br($e->getMessage()));
                $message = MessageBox::error(_('Fehler!'), $details);
                PageLayout::postMessage($message);
            }
        }
        
        if (empty($parameters)) {
            $parameters = array('' => '');
        }
        $this->parameters = $parameters;

        $clear_cache = sprintf('<a href="%s">%s</a>',
                               $this->url_for('client/clear_cache'), _('Token löschen'));
        $this->setInfoboxImage('infobox/administration.png');
        $this->addToInfobox('Aktionen', $clear_cache, 'icons/16/black/refresh.png');
    }

    function clear_cache_action()
    {
        $this->config->unsetValue(self::CONFIG_KEY_ACCESS_TOKEN);
        $this->cache->expire(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id);

        PageLayout::postMessage(MessageBox::success(_('Das OAuth-Token wurde gelöscht.')));
        $this->redirect('client');
    }

    private function request($resource, $parameters = array(), $content_type = 'application/json',
                             $method = 'GET', $signed = array(), $raw = false)
    {
        if (in_array('oauth', $signed)) {
            $client = $this->signed();
        } else {
            $client = new Zend_Http_Client;
        }
        
        if (in_array('studip', $signed)) {
            $client->setCookie('Seminar_Session', $_COOKIE['Seminar_Session']);
        }
        if (in_array('http', $signed)) {
            $client->setAuth(Request::get('http-username'), Request::get('http-password'),
                             Zend_Http_Client::AUTH_BASIC);
        }

        if ($client) {
            $uri  = $this->container['API_URL'] . "/$resource";
            $client->setHeaders('Content-Type', $content_type);
            if (!empty($parameters)) {
                if ($method === 'GET') {
                    $client->setParameterGet($parameters);
                } else {
                    $client->setParameterPost($parameters);
//                    $client->setRawData(http_build_query($parameters), $content_type);
                }
            }
            $client->setUri($uri);
            $client->setMethod($method);
            $response = $client->request();

            if ($raw or $response->isError()) {
                $result = sprintf("URL: %s\nStatus: %u %s\n%s\n%s",
                                  $client->getUri(true),
                                  $response->getStatus(), $response->getMessage(),
                                  $response->getHeadersAsString(), $response->getBody());
            } else {
                $result = $this->consumeResult($response->getBody(), $content_type);
            }
            if ($response->isError()) {
                throw new Exception($result, $response->getStatus());
            }
            return $result;
        }
    }


    private function signed()
    {
        $options = array(
            'callbackUrl'    => $this->container['CONSUMER_URL'] . '?' . $_SERVER['QUERY_STRING'],
            'siteUrl'        => $this->container['PROVIDER_URL'],
            'consumerKey'    => $this->container['CONSUMER_KEY'],
            'consumerSecret' => $this->container['CONSUMER_SECRET'],
        );
        $consumer = new Zend_Oauth_Consumer($options);

        $cache = StudipCacheFactory::getCache();
        $access_token = $this->config[self::CONFIG_KEY_ACCESS_TOKEN];

        if (!$access_token) {
            $request_token = $cache->read(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id);
            if (!$request_token) {
                $token = $consumer->getRequestToken();
                $cache->write(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id, serialize($token));
                $consumer->redirect();
            } else {
                try {
                    $token = @$consumer->getAccessToken($_GET, unserialize($request_token));

                    $access_token = serialize($token);
                    $this->config->store(self::CONFIG_KEY_ACCESS_TOKEN, $access_token);

                    PageLayout::postMessage(MessageBox::success(_('Zugriff erlaubt.')));
                } catch (Exception $e) {
                    PageLayout::postMessage(MessageBox::error(_('Zugriff verweigert.')));
                }
                $cache->expire(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id);
            }
        }

        if ($access_token) {
            $token = unserialize($access_token);
            $client = $token->getHttpClient($options);
        }

        return $client;
    }

    private function consumeResult($result, $content_type)
    {
        if ($content_type === 'application/json') {
            $result = json_decode($result, true);
        } elseif ($content_type === 'text/xml') {
            $result = json_decode(json_encode(simplexml_load_string($result)), true);
        } elseif ($content_type == 'text/php') {
            $result = unserialize($result);
        }
        $result = array_map_recursive('studip_utf8decode', $result);

        return $result;
    }
}