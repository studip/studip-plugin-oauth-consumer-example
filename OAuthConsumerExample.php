<?php
class OAuthConsumerExample extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();

        $navigation = new Navigation(_('OAuth Consumer'), PluginEngine::getLink($this, array(), 'client'));
        $navigation->setImage('header/resources.png');
        Navigation::addItem('/oauth_consumer', $navigation);
    }

    public function perform ($unconsumed_path)
    {
        require_once 'bootstrap.php';

        $this->addStylesheet('assets/form-settings.less');
        PageLayout::addScript($this->getPluginURL() . '/assets/oauth.js');

        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath() . DIRECTORY_SEPARATOR . 'app',
            rtrim(PluginEngine::getLink($this, array(), null, true), '/'),
            'client'
        );

        $dispatcher->plugin = $this;
        $dispatcher->container = $this->getContainer();
        $dispatcher->dispatch($unconsumed_path);
    }

    protected function getContainer()
    {
        $container = new Pimple\Container();

        # workaround to get an absolute URL
        URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

        $container['CONSUMER_URL'] = PluginEngine::getURL($this, array(), 'client/', true);

        if ($this->coreAPIEnabled()) {
            $container['core'] = array(
                'PROVIDER_URL'    => URLHelper::getURL('dispatch.php/api/oauth/', null, true),
                'API_URL'         => URLHelper::getURL('api.php/', null, true),
                'API_PROVIDER'    => 'CoreAPIProvider',
            );
        }

        if ($this->restIPEnabled()) {
            $container['rest.ip'] = array(
                'PROVIDER_URL'    => PluginEngine::getURL('restipplugin', array(), 'oauth/', true),
                'API_URL'         => PluginEngine::getURL('restipplugin', array(), 'api/', true),
                'API_PROVIDER'    => 'RestIPProvider',
            );
        }

        return $container;
    }

    public function coreAPIEnabled()
    {
        return Config::get()->API_ENABLED;
    }

    public function restIPEnabled()
    {
        return PluginEngine::getPlugin('RestipPlugin') !== null;
    }
}
