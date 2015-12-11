<?php
require_once 'lib/classes/UserConfig.class.php';

class ClientController extends StudipController
{
    public $content_types = array(
        'application/json',
        'text/php',
        'text/xml',
    );

    public $auth_types = array(
        'oauth'  => 'OAuth',
        'studip' => 'Stud.IP',
        'basic'  => 'HTTP',
    );

    const CACHE_KEY_REQUEST_TOKEN  = '/oauth/request_token/';
    const CACHE_KEY_DISCOVERY      = '/api-client/discovery/';
    const CACHE_DURATION_DISCOVERY = 604800; // 7 * 24 * 60 * 60
    const CONFIG_KEY_ACCESS_TOKEN  = 'OAUTH_CLIENT_ACCESS_TOKEN';

    public function before_filter(&$action, &$args)
    {
        if (!method_exists($this, $action . '_action')) {
            array_unshift($args, $action);
            $action = 'index';
        }

        parent::before_filter($action, $args);

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base.php'));
        Navigation::activateItem('/oauth_consumer');
        PageLayout::setTitle(_('OAuth Client'));

        # make container even more accessible
        $this->container = $this->dispatcher->container;

        $this->plugin = $this->dispatcher->plugin;

        $this->config = UserConfig::get($GLOBALS['user']->id);
        $this->cache  = StudipCacheFactory::getCache();

        $this->targets = $this->getTargets();
        $this->target  = Request::get('target', reset(array_keys($this->targets)));

        $config          = $this->container[$this->target];
        $this->provider  = new $config['API_PROVIDER']($config);

        $this->consumer_id = Request::option('consumer_id');
        if ($this->consumer_id) {
            $this->provider->setConsumer($this->consumer_id);
        }
    }

    public function index_action($auth_type = 'oauth')
    {
        if (empty($this->targets)) {
            $this->render_template('client/no-targets.php', $GLOBALS['template_factory']->open('layouts/base.php'));
            return;
        }

        $this->parameters = $this->extractParameters();
        $this->auth       = $auth_type;
        $this->discovery  = $this->getDiscovery($this->target);

        $resource = Request::get('resource');
        if ($resource) {
            try {
                $this->result = $this->request($resource, $this->parameters, Request::get('content_type'),
                                               Request::option('method'), $auth_type,
                                               !Request::int('consume'));
            } catch (Exception $e) {
                $details = explode(PHP_EOL, $e->getMessage());
                if ($e->getResponse()) {
                    $details[] = (string)$e->getResponse()->getBody();
                    $details[] = 'Request headers';
                    foreach ($e->getRequest()->getHeaders() as $key => $value) {
                        $details[] = sprintf('%s = "%s"', htmlReady($key), htmlReady(implode(', ', $value)));
                    }
                    $details[] = 'Response headers';
                    foreach ($e->getResponse()->getHeaders() as $key => $value) {
                        $details[] = sprintf('%s = "%s"', htmlReady($key), htmlReady(implode(', ', $value)));
                    }
                }

                $message = MessageBox::error(_('Fehler!'), $details);
                PageLayout::postMessage($message);
            }
        }

        $this->setupSidebar($this->target, $auth_type);
    }

    public function discovery_action($auth_type)
    {
        $result = $this->request('discovery', array(), 'application/json', 'get', 'studip', false);
        $discovery = $this->provider->normalizeDiscovery($result);
        $this->setDiscovery($this->target, $discovery);
        
        $this->relocate('client/' . $auth_type, array('target' => $this->target));
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
        $this->relocate('client', array('target' => Request::get('target')));
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

        $this->relocate('client/oauth', array(
            'target'      => Request::get('target'),
            'consumer_id' => Request::option('consumer_id'),
        ));
    }

    protected function request($resource, $parameters = array(), $content_type = 'application/json',
                             $method = 'GET', $auth_type, $raw = false)
    {
        $request_options = array();

        $defaults = array(
            'base_url' => $this->provider->getConfig('API_URL'),
            'headers'  => array(
                'Content-Type' => $content_type,
            ),
        );
        $client = new GuzzleHttp\Client($defaults);

        if ($auth_type === 'oauth') {
            $request_options['auth'] = 'oauth';

            $this->attach_oauth($client);
        } elseif ($auth_type === 'studip') {
            $request_options['cookies'] = array(
                'Seminar_Session' => $_COOKIE['Seminar_Session'],
            );
        } elseif ($auth_type === 'basic') {
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
                    if ($key) {
                        $query->set($key, $value);
                    }
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

                $url = URLHelper::getURL($this->provider->getConfig('PROVIDER_URL') . 'authorize', array(
                    'oauth_token'    => $token['oauth_token'],
                    'oauth_callback' => URLHelper::getURL($this->container['CONSUMER_URL'] . 'success', array(
                        'target'      => $this->target,
                        'consumer_id' => $this->provider->getConsumerId(),
                    )),
                ));
                $this->redirect($url);
            }
        }

        $oauth = new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->provider->getconfig('CONSUMER_KEY'),
            'consumer_secret' => $this->provider->getConfig('CONSUMER_SECRET'),
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
            'base_url' => $this->provider->getConfig('PROVIDER_URL'),
            'defaults' => array(
                'auth' => 'oauth',
            ),
        ));
        $token_client->getEmitter()->attach(new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->provider->getConfig('CONSUMER_KEY'),
            'consumer_secret' => $this->provider->getConfig('CONSUMER_SECRET'),
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
            'base_url' => $this->provider->getConfig('PROVIDER_URL'),
            'defaults' => array(
                'auth' => 'oauth',
            ),
        ));
        $token_client->getEmitter()->attach(new GuzzleHttp\Subscriber\Oauth\Oauth1(array(
            'consumer_key'    => $this->provider->getconfig('CONSUMER_KEY'),
            'consumer_secret' => $this->provider->getConfig('CONSUMER_SECRET'),
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

    protected function getTargets()
    {
        $targets = array();
        if ($this->plugin->coreAPIEnabled()) {
            $targets['core'] = _('Kern');
        }
        if ($this->plugin->restIPEnabled()) {
            $targets['rest.ip'] = _('Rest.IP');
        }
        return $targets;
    }

    protected function extractParameters()
    {
        $parameters = Request::getArray('parameters');
        if (!empty($parameters) && !empty($parameters['name']) && !empty($parameters['value'])) {
            foreach ($parameters['name'] as $index => $name) {
                if (empty($name)) {
                    unset($parameters['name'][$index], $parameters['value'][$index]);
                }
            }
            $parameters = empty($parameters['name'])
                        ? array()
                        : array_combine($parameters['name'], $parameters['value']);
        }
        if (empty($parameters)) {
            $parameters = array('' => '');
        }
        return $parameters;
    }

    protected function getDiscovery($target)
    {
        $cached = $this->cache->read(self::CACHE_KEY_DISCOVERY . $target);
        return $cached
            ? unserialize($cached)
            : false;
    }

    protected function setDiscovery($target, $discovery)
    {
        $this->cache->write(self::CACHE_KEY_DISCOVERY . $target, serialize($discovery), self::CACHE_DURATION_DISCOVERY);
    }

    protected function setupSidebar($target, $auth)
    {
        $targets = new ViewsWidget();
        $targets->setTitle(_('Ziel-API'));
        foreach ($this->targets as $key => $label) {
            $targets->addLink($label, $this->url_for('client?target=' . $key))->setActive($target === $key);
        }
        Sidebar::get()->addWidget($targets);

        $auths = new OptionsWidget();
        $auths->setTitle(_('Authorisierung'));
        foreach ($this->auth_types as $key => $label) {
            $auths->addRadioButton($label, $this->url_for('client/' . $key, compact('target')), $auth === $key);
        }
        Sidebar::get()->addWidget($auths);

        $actions = new ActionsWidget();
        if ($this->getDiscovery($target)) {
            $actions->addLink(_('Disovery aktualisieren'), $this->url_for('client/discovery/'. $auth, compact('target')), 'icons/16/blue/literature.png');
        } else {
            $actions->addLink(_('Disovery laden'), $this->url_for('client/discovery/'. $auth, compact('target')), 'icons/16/blue/literature.png');
        }

        if ($auth === 'oauth') {
            if ($this->getRequestToken()) {
                $actions->addLink(_('Request-Token anzeigen'), $this->url_for('client/token/request'), 'icons/16/blue/visibility-visible.png')->asDialog();
                $actions->addLink(_('Request-Token löschen'), $this->url_for('client/clear_cache/request', compact('target')), 'icons/16/blue/trash.png');
            }
            if ($this->getAccessToken()) {
                $actions->addLink(_('Access-Token anzeigen'), $this->url_for('client/token/access'), 'icons/16/blue/visibility-visible.png')->asDialog();
                $actions->addLink(_('Access-Token löschen'), $this->url_for('client/clear_cache/access', compact('target')), 'icons/16/blue/trash.png');
            }
        }
        Sidebar::get()->addWidget($actions);
    }
}
