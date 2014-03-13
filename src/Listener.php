<?php

namespace SiteMaster\Plugins\Auth_unl;

use SiteMaster\Core\Config;
use SiteMaster\Core\Events\GetAuthenticationPlugins;
use SiteMaster\Core\Events\Navigation\MainCompile;
use SiteMaster\Core\Events\RoutesCompile;
use SiteMaster\Core\Events\User\Search;
use SiteMaster\Core\Plugin\PluginListener;
use SiteMaster\Core\User\Session;

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

    public function onUserSearch(Search $event)
    {
        $results = array_merge(
            $this->getSearchResults($event->getSearchTerm()),
            $this->getSearchResultsForUID($event->getSearchTerm())
        );

        foreach ($results as $user) {
            if (empty($user['uid'])) {
                continue;
            }

            $uid = $user['uid'];
            
            $email = '';
            if (isset($user['mail'][0])) {
                $email = $user['mail'][0];
            }
            
            $first_name = '';
            if (isset($user['givenName'][0])) {
                $first_name = $user['givenName'][0];
            }
            
            $last_name = '';
            if (isset($user['sn'][0])) {
                $last_name = $user['sn'][0];
            }
            
            $event->addResult('UNL', $uid, $email, $first_name, $last_name);
        }
    }
    
    protected function getSearchResults($term)
    {
        $url = 'http://directory.unl.edu/?q=' . urlencode($term) . '&format=json';

        if (!$data = @file_get_contents($url)) {
            return array();
        }

        if (!$json = json_decode($data, true)) {
            return array();
        }
        
        return $json;
    }
    
    protected function getSearchResultsForUID($term)
    {
        $url = 'http://directory.unl.edu/?uid=' . urlencode($term) . '&format=json';

        if (!$data = @file_get_contents($url)) {
            return array();
        }

        if (!$json = json_decode($data, true)) {
            return array();
        }
        
        return array($json);
    }

    /**
     * Compile primary navigation
     *
     * @param MainCompile $event
     */
    public function onNavigationMainCompile(MainCompile $event)
    {
        if (Session::getCurrentUser()) {
            $event->addNavigationItem(Config::get('URL') . 'auth/unl/logout/', 'Logout');
        } else {
            $event->addNavigationItem(Config::get('URL') . 'auth/unl/', 'Login');
        }
    }
}