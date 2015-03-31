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

        $container['CONSUMER_KEY'] = 'ec17139d5c9a1f3e88f48960d3faec5105375ddc2';
        $container['CONSUMER_SECRET'] = '9d6252273b4cb08396f25f3f42332a5e';

        # workaround to get an absolute URL
        URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

        $container['PROVIDER_URL'] = PluginEngine::getURL('restipplugin', array(), 'oauth/', true);
        $container['API_URL'] = PluginEngine::getURL('restipplugin', array(), 'api/', true);

        $container['CONSUMER_URL'] = PluginEngine::getURL($this, array(), 'client/', true);

        return $container;
    }
}
