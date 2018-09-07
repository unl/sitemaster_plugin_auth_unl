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
    
    public static $directory_url = 'https://directory.unl.edu/';
    
    public function __construct()
    {
        $this->setUpClient();
    }

    /**
     * Authenticate the user
     */
    public function authenticate()
    {

        \phpCAS::forceAuthentication();
        
        if (!\phpCAS::getUser()) {
            throw new RuntimeException('Unable to authenticate', 403);
        }
        
        $user = $this->getUser(\phpCAS::getUser());
        $plugin = PluginManager::getManager()->getPluginInfo('auth_unl');
        
        Session::logIn($user, $plugin->getProviderMachineName());
        
        if (isset($_GET['r'])) {
            Controller::redirect($_GET['r']);
        }
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
            //UNL users default to not private
            $user->is_private = User::PRIVATE_NO;
            
            //Update the user with their latest information.
            if (isset($info['email']) && !empty($info['email'])) {
                $user->email = $info['email'];
            }
            
            if (isset($info['first_name'])) {
                $user->first_name = $info['first_name'];
            }

            if (isset($info['last_name'])) {
                $user->last_name = $info['last_name'];
            }

            $user->save();
            
        } else {
            //Create a new user
            $user = User::createUser($uid, $plugin->getProviderMachineName(), $info);
        }
        
        return $user;
    }
    
    public function autoLogin()
    {
        if (isset($_GET['format']) && $_GET['format'] != 'html') {
            //Don't auto-login on non-html format requests
            return;
        }
        
        if (!array_key_exists('unl_sso', $_COOKIE)) {
            //No unl_sso cookie was found, no need to auto-login.
            return;
        }

        if (\SiteMaster\Core\User\Session::getCurrentUser()) {
            //We are already logged in, no need to auto-login
            return;
        }
        
        //Everything looks good.  Log in!
        $result = \phpCAS::checkAuthentication();
        
        if ($result) {
            $uid = \phpCAS::getUser();
            $user = $this->getUser($uid);
            $plugin = PluginManager::getManager()->getPluginInfo('auth_unl');
            Session::logIn($user, $plugin->getProviderMachineName());
        } else {
            //Be a good citizen and delete the unl_sso cookie (no longer logged into SSO)
            setcookie('unl_sso', '', time()-3600);
        }
    }
    
    public function singleLogOut()
    {
        \phpCAS::handleLogoutRequests(false);
    }
    
    public function logout()
    {
        \phpCAS::logoutWithRedirectService(Util::getAbsoluteBaseURL());
    }

    /**
     * Set up the SimpleCAS client
     */
    public function setUpClient()
    {
        $plugin = PluginManager::getManager()->getPluginInfo('auth_unl');

        $options = $plugin->getOptions();
        
        if (!file_exists($options['CERT_PATH'])) {
            throw new \Exception('The current CERT_PATH does not exist: ' . $options['CERT_PATH']);
        }
        
        if (!\phpCAS::isInitialized()) {
            \phpCAS::client(CAS_VERSION_2_0, 'shib.unl.edu', 443, '/idp/profile/cas');
            \phpCAS::setCasServerCACert($options['CERT_PATH']);
        }
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