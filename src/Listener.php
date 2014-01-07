<?php

namespace SiteMaster\Plugins\Auth_Unl;

use SiteMaster\Events\GetAuthenticationPlugins;
use SiteMaster\Events\RoutesCompile;
use SiteMaster\Plugin\PluginListener;

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