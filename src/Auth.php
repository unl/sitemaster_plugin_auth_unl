<?php
namespace SiteMaster\Plugins\Auth_unl;

use SiteMaster\Core\Config;
use SiteMaster\Core\Controller;
use SiteMaster\Core\Plugin\PluginManager;
use SiteMaster\Core\User\Session;
use SiteMaster\Core\User\User;
use SiteMaster\Core\Util;

class Auth
{
    /**
     * @var array
     */
    protected $options = array();
    
    public static $directory_url = 'http://directory.unl.edu/';

    /**
     * Authenticate the user
     */
    public function authenticate()
    {
        $client = $this->getClient();
        
        $client->forceAuthentication();

        if (!$client->isAuthenticated()) {
            throw new RuntimeException('Unable to authenticate', 403);
        }
        
        $user = $this->getUser($client->getUsername());

        Session::logIn($user);
        Controller::redirect($user->getURL());
    }

    /**
     * Get the current user (will create a user if none exist)
     *
     * @param $uid string the UID of the user
     * @return bool|User
     */
    protected function getUser($uid)
    {
        $uid      = trim(strtolower($uid));
        $plugin   = PluginManager::getManager()->getPluginInfo('auth_unl');
        
        if (null == $uid) {
            return false;
        }

        $user = User::getByUIDAndProvider($uid, $plugin->getProviderMachineName());
        $info = self::getUserInfo($uid);
        
        if ($user) {
            //Update the user with their latest information.
            if (!empty($info['email'])) {
                $user->email = $info['email'];
            }
            $user->first_name = $info['first_name'];
            $user->last_name = $info['last_name'];
            
        } else {
            //Create a new user
            $user = User::createUser($uid, $plugin->getProviderMachineName(), $info);
        }
        
        return $user;
    }
    
    public function autoLogin()
    {
        if (!array_key_exists('unl_sso', $_COOKIE)) {
            //No unl_sso cookie was found, no need to auto-login.
            return;
        }

        if (\SiteMaster\Core\User\Session::getCurrentUser()) {
            //We are already logged in, no need to auto-login
            return;
        }

        $client = $this->getClient();
        
        //Everything looks good.  Log in!
        $client->gatewayAuthentication();
        
        if ($client->isAuthenticated()) {
            $uid = $client->getUsername();
            $user = $this->getUser($uid);
            Session::logIn($user);
        }
    }
    
    public function singleLogOut()
    {
        $client = $this->getClient();
        $client->handleSingleLogOut();
    }
    
    public function logout()
    {
        $client = $this->getClient();
        $client->logout(Util::getAbsoluteBaseURL());
    }

    /**
     * Get the SimpleCAS client
     *
     * @return \SimpleCAS
     */
    public function getClient()
    {
        $options = array(
            'hostname' => 'login.unl.edu',
            'port'     => 443,
            'uri'      => 'cas'
        );
        
        $protocol = new \SimpleCAS_Protocol_Version2($options);

        /**
         * We need to customize the request to use CURL because 
         * php5.4 and ubuntu systems can't verify ssl connections 
         * without specifying a CApath.  CURL does this automatically
         * based on the system, but openssl does not.
         * 
         * It looks like this will be fixed in php 5.6
         * https://wiki.php.net/rfc/tls-peer-verification
         */
        $request = new \HTTP_Request2();
        $request->setConfig('adapter', 'HTTP_Request2_Adapter_Curl');
        $protocol->setRequest($request);

        /**
         * Set up the session cache mapping
         */
        $cache_driver = new \Stash\Driver\FileSystem();

        $cache_driver->setOptions(array(
                //Scope the cache to the current application only.
                'path' => Config::get('CACHE_DIR') . '/simpleCAS_map',
        ));
        
        $session_map = new \SimpleCAS_SLOMap($cache_driver);
        
        $protocol->setSessionMap($session_map);

        return \SimpleCAS::client($protocol);
    }


    /**
     * Get a user's information from directory.unl.edu
     * 
     * @param string $uid
     * @return array
     */
    public static function getUserInfo($uid)
    {
        $info = array();
        
        if (!$json = @file_get_contents(self::$directory_url . '?uid=' . $uid . '&format=json')) {
            return $info;
        }
        
        if (!$json = json_decode($json, true)) {
            return $info;
        }
        
        $map = array(
            'givenName' => 'first_name',
            'sn' => 'last_name',
            'mail' => 'email'
        );
        
        foreach ($map as $from => $to) {
            if (isset($json[$from][0])) {
                $info[$to] = $json[$from][0];
            }
        }
        
        return $info;
    }
}