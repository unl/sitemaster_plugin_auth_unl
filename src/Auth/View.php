<?php
namespace SiteMaster\Plugins\Auth_unl\Auth;

use SiteMaster\Core\Config;
use SiteMaster\Core\Controller;
use SiteMaster\Core\ViewableInterface;
use SiteMaster\Plugins\Auth_unl\Auth;

class View implements ViewableInterface
{
    protected $auth;
    /**
     * @param array $options
     */
    function __construct($options = array())
    {
        $this->auth = new Auth;
        
        if (strpos($options['current_url'], 'logout') !== false) {
            //handle callback
            $this->auth->logout();
        }

        //Check if we need to log out.
        $this->auth->singleLogOut();

        //Authenticate
        $this->auth->authenticate();

        $user = \SiteMaster\Core\User\Session::getCurrentUser();
        
        Controller::redirect($user->getURL());
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