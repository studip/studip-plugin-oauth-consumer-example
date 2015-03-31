<?php
require_once 'lib/classes/UserConfig.class.php';

class ClientController extends StudipController
{
    public $content_types = array(
        'application/json',
        'text/php',
        'text/xml',
    );

    const CACHE_KEY_REQUEST_TOKEN = '/oauth/request_token/';
    const CONFIG_KEY_ACCESS_TOKEN = 'OAUTH_CLIENT_ACCESS_TOKEN';

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base.php'));
        Navigation::activateItem('/oauth_consumer');
        PageLayout::setTitle(_('OAuth Client'));

        # make container even more accessible
        $this->container = $this->dispatcher->container;

        $this->config = UserConfig::get($GLOBALS['user']->id);
        $this->cache  = StudipCacheFactory::getCache();
    }

    public function index_action()
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
                $details = explode(PHP_EOL, $e->getMessage());
                $message = MessageBox::error(_('Fehler!'), $details);
                PageLayout::postMessage($message);
            }
        }

        if (empty($parameters)) {
            $parameters = array('' => '');
        }
        $this->parameters = $parameters;

        $actions = new ActionsWidget();
        if ($this->getRequestToken()) {
            $actions->addLink(_('Request-Token anzeigen'), $this->url_for('client/token/request'), 'icons/16/blue/visibility-visible.png')->asDialog();
            $actions->addLink(_('Request-Token löschen'), $this->url_for('client/clear_cache/request'), 'icons/16/blue/refresh.png');
        }
        if ($this->getAccessToken()) {
            $actions->addLink(_('Access-Token anzeigen'), $this->url_for('client/token/access'), 'icons/16/blue/visibility-visible.png')->asDialog();
            $actions->addLink(_('Access-Token löschen'), $this->url_for('client/clear_cache/access'), 'icons/16/blue/refresh.png');
        }
        Sidebar::get()->addWidget($actions);
    }

    public function token_action($type)
    {
        $token = $type === 'access'
               ? $this->getAccessToken()
               : $this->getRequestToken();
        $this->render_text(formatReady(json_encode($token, JSON_PRETTY_PRINT)));
    }

    public function clear_cache_action($type = 'all')
    {
        if ($type === 'all' || $type === 'request') {
            $this->setRequestToken(false);
        }
        if ($type === 'all' || $type === 'access') {
            $this->setAccessToken(false);
        }

        PageLayout::postMessage(MessageBox::success(_('Das OAuth-Token wurde gelöscht.')));
        $this->redirect('client');
    }
    
    public function success_action()
    {
        if (Request::submitted('oauth_token')) {
            $token = $this->requestOauthAccessToken($this->getRequestToken(), Request::get('oauth_verifier'));
            $this->setAccessToken($token);

            PageLayout::postMessage(MessageBox::success(_('Zugriff erlaubt.')));
        } else {
            PageLayout::postMessage(MessageBox::error(_('Zugriff verweigert.')));
        }

        $this->setRequestToken(false);
        
        $this->redirect('client');
    }

    protected function request($resource, $parameters = array(), $content_type = 'application/json',
                             $method = 'GET', $signed = array(), $raw = false)
    {
        $request_options = array();

        $defaults = array(
            'base_url' => $this->container['API_URL'],
            'headers'  => array(
                'Content-Type' => $content_type,
            ),
        );
        $client = new GuzzleHttp\Client($defaults);

        if (in_array('oauth', $signed)) {
            $request_options['auth'] = 'oauth';

            $this->attach_oauth($client);
        }

        if (in_array('studip', $signed)) {
            $request_options['cookies'] = array(
                'Seminar_Session' => $_COOKIE['Seminar_Session'],
            );
        }

        if (in_array('http', $signed)) {
            $request_options['auth'] = array(
                Request::get('http-username'),
                Request::get('http-password'),
            );
        }

        if ($client) {
            $request = $client->createRequest($method, $resource, $request_options);
            if ($method === 'GET') {
                $query = $request->getQuery();
                foreach ($parameters as $key => $value) {
                    $query->set($key, $value);
                }
            } else {
                $postBody = $request->getBody();
                foreach ($parameters as $key => $value) {
                    $postBody->setField($key, $value);
                }
            }
            $response = $client->send($request);

            if ($raw || $response->getStatusCode() != 200) {
                $result = sprintf("URL: %s\nStatus: %u %s\n%s\n%s",
                                  $request->getUrl(),
                                  $response->getStatusCode(), $response->getReasonPhrase(),
                                  serialize($response->getHeaders()), $response->getBody());
            } else {
                $result = $this->consumeResult($response->getBody(), $content_type);
            }
            if ($response->getStatusCode() != 200) {
                throw new Exception($result, $response->getStatusCode());
            }
            return $result;
        }
    }


    protected function attach_oauth(&$client)
    {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            $request_token = $this->getRequestToken();
            if (!$request_token) {
                $token = $this->requestOauthRequestToken();

                $this->setRequestToken($token);

                $url = URLHelper::getURL($this->container['PROVIDER_URL'] . 'authorize', array(
                    'oauth_token'    => $token['oauth_token'],
                    'oauth_callback' => $this->url_for('client/success'),
                ));
                $this->redirect($url);
            }
        }

        $oauth = new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->container['CONSUMER_KEY'],
            'consumer_secret' => $this->container['CONSUMER_SECRET'],
            'token'           => $access_token['oauth_token'],
            'token_secret'    => $access_token['oauth_token_secret'],
        ));
        $client->getEmitter()->attach($oauth);
    }

    protected function getRequestToken()
    {
        $cached = $this->cache->read(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id);
        return $cached
            ? unserialize($cached)
            : false;
    }
    
    protected function setRequestToken($token)
    {
        if ($token === false) {
            $this->cache->expire(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id);
        } else {
            $this->cache->write(self::CACHE_KEY_REQUEST_TOKEN . $GLOBALS['user']->id, serialize($token), $token['xoauth_token_ttl'] ?: 60 * 60);
        }
    }

    protected function requestOauthRequestToken()
    {
        $token_client = new GuzzleHttp\Client(array(
            'base_url' => $this->container['PROVIDER_URL'],
            'defaults' => array(
                'auth' => 'oauth',
            ),
        ));
        $token_client->getEmitter()->attach(new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->container['CONSUMER_KEY'],
            'consumer_secret' => $this->container['CONSUMER_SECRET'],
        )));

        $request  = $token_client->createRequest('POST', 'request_token');
        $response = $token_client->send($request);
        $body     = (string)$response->getBody();

        parse_str($body, $values);

        return $values;
    }

    protected function getAccessToken()
    {
        $cached = $this->config[self::CONFIG_KEY_ACCESS_TOKEN];
        return $cached
            ? unserialize($cached)
            : false;
    }
    
    protected function setAccessToken($token)
    {
        if ($token === false) {
            $this->config->unsetValue(self::CONFIG_KEY_ACCESS_TOKEN);
        } else {
            $this->config->store(self::CONFIG_KEY_ACCESS_TOKEN, serialize($token));
        }
    }

    protected function requestOauthAccessToken($request_token, $verifier)
    {
        $token_client = new GuzzleHttp\Client(array(
            'base_url' => $this->container['PROVIDER_URL'],
            'defaults' => array(
                'auth' => 'oauth',
            ),
        ));
        $token_client->getEmitter()->attach(new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->container['CONSUMER_KEY'],
            'consumer_secret' => $this->container['CONSUMER_SECRET'],
            'token'           => $request_token['oauth_token'],
            'token_secret'    => $request_token['oauth_token_secret'],
            'verifier'        => $verifier,
        )));

        $request = $token_client->createRequest('GET', 'access_token');
        try {
            $response = $token_client->send($request);
        } catch (Exception $e) {
            PageLayout::postMessage(MessageBox::info((string)$e->getResponse()->getBody()));
            return false;
        }
        $body = (string)$response->getBody();

        parse_str($body, $values);

        return $values;
    }

    protected function consumeResult($result, $content_type)
    {
        if ($content_type === 'application/json') {
            $result = json_decode($result, true);
        } elseif ($content_type === 'text/xml') {
            $result = json_decode(json_encode(simplexml_load_string($result)), true);
        } elseif ($content_type == 'text/php') {
            $result = unserialize($result);
        }
        $result = studip_utf8decode($result);

        return $result;
    }
}
