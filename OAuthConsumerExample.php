<?php
require_once 'bootstrap.php';

class OAuthConsumerExample extends StudIPPlugin implements SystemPlugin {

    function __construct() {
        parent::__construct();

        $navigation = new Navigation(_('OAuth Consumer'), PluginEngine::getLink($this, array(), 'client'));
        $navigation->setImage("header/resources.png");
        Navigation::addItem('/oauth_consumer', $navigation);
    }

    function initialize()
    {
        PageLayout::addStylesheet($this->getPluginURL() . '/assets/form-settings.css');
        PageLayout::addScript($this->getPluginURL() . '/assets/oauth.js');
    }

    function perform ($unconsumed_path)
    {
        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath() . DIRECTORY_SEPARATOR . 'app',
            rtrim(PluginEngine::getLink($this, array(), null), '/'),
            'client'
        );

        $dispatcher->plugin = $this;
        $dispatcher->container = $this->getContainer();
        $dispatcher->dispatch($unconsumed_path);
    }

    function getContainer()
    {
        require_once dirname(__FILE__) . '/vendor/pimple/lib/Pimple.php';
        $container = new Pimple();

        $container['CONSUMER_KEY'] = '9d71af247ef85b481084d77c00a13bfe04f6df62f';
        $container['CONSUMER_SECRET'] = '2070fb311f1ee3bac45516ebc8f227e9';

        # workaround to get an absolute URL
        URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

        $container['PROVIDER_URL'] = PluginEngine::getURL("restipplugin", array(), 'oauth');
        $container['API_URL'] = PluginEngine::getURL("restipplugin", array(), 'api');

        $container['CONSUMER_URL'] = PluginEngine::getURL($this, array(), 'client');

        return $container;
    }
}
