<?php
require_once 'bootstrap.php';

class OAuthConsumerExample extends StudIPPlugin implements SystemPlugin {

    function __construct() {
        parent::__construct();

        $config = Config::getInstance();
        if (!$config['OAUTH_ENABLED']) {
            return;
        }

        if (!$this->checkEnvironment()) {
            $message   = _('Das OAuth-Plugin ist aktiviert, aber nicht f�r die Rolle "Nobody" freigegeben.');
            $details   = array();
            $details[] = _('Dies behindert die Kommunikation externer Applikationen mit dem System.');
            $details[] = sprintf(_('Klicken Sie <a href="%s">hier</a>, um die Rollenzuweisung zu bearbeiten.'),
                               URLHelper::getLink('dispatch.php/admin/role/assign_plugin_role/' . $this->getPluginId()));
            PageLayout::postMessage(Messagebox::info($message, $details));
        }

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

        $container['CONSUMER_KEY'] = '1d918110489350d4ff682c48f247a34804f2268ef';
        $container['CONSUMER_SECRET'] = '07d9acf83e15069f54476fb2f6e13583';

        # workaround to get an absolute URL
        URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

        $container['PROVIDER_URL'] = PluginEngine::getURL("restipplugin", array(), 'oauth');
        $container['API_URL'] = PluginEngine::getURL("restipplugin", array(), 'api');

        $container['CONSUMER_URL'] = PluginEngine::getURL($this, array(), 'client');

        return $container;
    }

    function checkEnvironment() {
        # TODO performance - use cache on success ?
        $role_persistence = new RolePersistence;
        $plugin_roles     = $role_persistence->getAssignedPluginRoles($this->getPluginId());
        $role_names       = array_map(function ($role) { return $role->getRolename(); }, $plugin_roles);

        return in_array('Nobody', $role_names);
    }
}
