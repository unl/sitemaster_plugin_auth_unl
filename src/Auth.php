<?php
namespace SiteMaster\Plugins\Auth_unl;

use SiteMaster\Core\Config;
use SiteMaster\Core\Plugin\PluginManager;
use SiteMaster\Core\User\Session;
use SiteMaster\Core\User\User;
use SiteMaster\Core\ViewableInterface;

class Auth implements ViewableInterface
{
    /**
     * @var array
     */
    protected $options = array();
    
    public static $directory_url = 'http://directory.unl.edu/';

    /**
     * @param array $options
     */
    function __construct($options = array())
    {
        $this->authenticate();
        
        if (strpos($options['current_url'], 'logout') !== false) {
            //handle callback
            $this->logout();
        }
    }

    /**
     * Authenticate the user
     */
    public function authenticate()
    {
        $client = $this->getClient();
        $plugin = PluginManager::getManager()->getPluginInfo('auth_unl');
        
        $client->forceAuthentication();

        if (!$client->isAuthenticated()) {
            throw new RuntimeException('Unable to authenticate', 403);
        }

        $uid = trim(strtolower($client->getUsername()));
        if (!$user = User::getByUIDAndProvider($client->getUsername(), $plugin->getProviderMachineName())) {
            $info = self::getUserInfo($uid);
            
            $user = User::createUser($client->getUsername(), $plugin->getProviderMachineName(), $info);
        }

        Session::logIn($user);
    }
    
    public function logout()
    {
        $client = $this->getClient();
        $client->logout(Config::get('URL'));
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

    /**
     * The URL for this page
     *
     * @return string
     */
    public function getURL()
    {
        return Config::get('URL') . 'auth/unl/';
    }

    /**
     * The page title for this page
     *
     * @return string
     */
    public function getPageTitle()
    {
        return "UNL Auth";
    }
}