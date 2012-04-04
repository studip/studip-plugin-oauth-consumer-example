<?php
spl_autoload_register(function ($name) {
    $file = str_replace('\\', '/', $name);
    $file = str_replace('Zend/', '', $file);
    $file = '/vendor/zf2/' . $file . '.php';
    $path = realpath(dirname(__FILE__).'/../..');
    @include $path . $file;
}, false, true);

class ClientController extends StudipController
{

    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        Navigation::activateItem('/oauth_consumer');
        PageLayout::setTitle(_('OAuth Client'));

        # make container even more accessible
        $this->container = $this->dispatcher->container;
    }

    function index_action() {
        $resource = Request::get('resource');
        if ($resource) {
            try {
                $this->result = $this->request($resource, Request::option('format'),
                                               Request::option('method'), Request::int('signed'),
                                               !Request::int('consume'));
            } catch (Exception $e) {
                $details = array(nl2br($e->getMessage()));
                $message = MessageBox::error(_('Fehler!'), $details);
                PageLayout::postMessage($message);
            }
        }

        $clear_cache = sprintf('<a href="%s">%s</a>',
                               $this->url_for('client/clear_cache'), _('Token l�schen'));
        $this->setInfoboxImage('infobox/administration.png');
        $this->addToInfobox('Aktionen', $clear_cache, 'icons/16/black/refresh.png');
    }

    const REQUEST_TOKEN = '/oauth/request_token/';
    const ACCESS_TOKEN = '/oauth/access_token/';

    function clear_cache_action() {
        $cache = StudipCacheFactory::getCache();
        $cache->expire(self::ACCESS_TOKEN . $GLOBALS['user']->id);
        $cache->expire(self::REQUEST_TOKEN . $GLOBALS['user']->id);

        PageLayout::postMessage(MessageBox::success(_('Das OAuth-Token wurde gel�scht.')));
        $this->redirect('client');
    }

    private function request($resource, $format = 'php', $method = 'GET', $signed = false, $raw = false) {
        if ($signed) {
            $client = $this->signed();
        } else {
            $client = new \Zend\Http\Client;
        }

        if ($client) {
            $uri  = $this->container['API_URL'] . "/$resource.$format";
            $client->setUri($uri);
            $client->setMethod($method);
            $response = $client->send();

            if ($raw or $response->isClientError()) {
                $result = sprintf("URL: %s\nStatus: %u %s\n%s\n%s",
                                  $client->getUri(),
                                  $response->getStatusCode(), $response->getReasonPhrase(),
                                  $response->headers()->toString(), $response->getBody());
            } else {
                $result = $this->consumeResult($response->getBody(), $format);
            }
            if ($response->isClientError()) {
                throw new Exception($result, $response->getStatusCode());
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
        $consumer = new \Zend\OAuth\Consumer($options);


        $cache = StudipCacheFactory::getCache();
        $access_token = $cache->read(self::ACCESS_TOKEN . $GLOBALS['user']->id);

        if (!$access_token) {
            $request_token = $cache->read(self::REQUEST_TOKEN . $GLOBALS['user']->id);
            if (!$request_token) {
                $token = $consumer->getRequestToken();
                $cache->write(self::REQUEST_TOKEN . $GLOBALS['user']->id, serialize($token));
                $consumer->redirect();
            } else {
                try {
                    $token = $consumer->getAccessToken($_GET, unserialize($request_token));
                    $access_token = serialize($token);
                    $cache->write(self::ACCESS_TOKEN . $GLOBALS['user']->id, $access_token);
                    $cache->expire(self::REQUEST_TOKEN . $GLOBALS['user']->id);
                    PageLayout::postMessage(MessageBox::success(_('Zugriff erlaubt.')));
                } catch (Exception $e) {
                    $cache->expire(self::REQUEST_TOKEN . $GLOBALS['user']->id);
                    PageLayout::postMessage(MessageBox::error(_('Zugriff verweigert.')));
                }
            }
        }

        if ($access_token) {
            $token = unserialize($access_token);
            $client = $token->getHttpClient($options);
        }

        return $client;
    }

    private function consumeResult($result, $format) {
        if ($format === 'json') {
            $result = json_decode($result, true);
            $result = array_map_recursive('studip_utf8decode', $result);
        } elseif ($format === 'php') {
            $result = unserialize($result);
        } elseif ($format === 'xml') {
            $result = json_decode(json_encode(simplexml_load_string($result)), true);
        }

        return $result;
    }

    /**
     * Spawns a new infobox variable on this object, if neccessary.
     **/
    private function populateInfobox()
    {
        if (!isset($this->infobox)) {
            $this->infobox = array(
                'picture' => 'blank.gif',
                'content' => array()
            );
        }
    }

    /**
     * Sets the header image for the infobox.
     *
     * @param String $image Image to display, path is relative to :assets:/images
     **/
    function setInfoBoxImage($image)
    {
        $this->populateInfobox();

        $this->infobox['picture'] = $image;
    }

    /**
     * Adds an item to a certain category section of the infobox. Categories
     * are created in the order this method is invoked. Multiple occurences of
     * a category will add items to the category.
     *
     * @param String $category The item's category title used as the header
     * above displayed category - write spoken not
     * tech language ^^
     * @param String $text The content of the item, may contain html
     * @param String $icon Icon to display in front the item, path is
     * relative to :assets:/images
     **/
    function addToInfobox($category, $text, $icon = 'blank.gif')
    {
        $this->populateInfobox();

        $infobox = $this->infobox;

        if (!isset($infobox['content'][$category])) {
            $infobox['content'][$category] = array(
                'kategorie' => $category,
                'eintrag' => array(),
            );
        }
        $infobox['content'][$category]['eintrag'][] = compact('icon', 'text');

        $this->infobox = $infobox;
    }
}