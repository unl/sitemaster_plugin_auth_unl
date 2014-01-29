<?php

namespace SiteMaster\Plugins\Auth_unl;

use SiteMaster\Core\Events\GetAuthenticationPlugins;
use SiteMaster\Core\Events\RoutesCompile;
use SiteMaster\Core\Plugin\PluginListener;

class Listener extends PluginListener
{
    public function onRoutesCompile(RoutesCompile $event)
    {
        $event->addRoute('/^auth\/unl\/$/', __NAMESPACE__ . '\Auth');
        $event->addRoute('/^auth\/unl\/logout\/$/', __NAMESPACE__ . '\Auth');
    }

    public function onGetAuthenticationPlugins(GetAuthenticationPlugins $event)
    {
        $event->addPlugin($this->plugin);
    }
}