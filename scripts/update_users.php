<?php
ini_set('display_errors', true);

//Initialize all settings and autoloaders
require_once(__DIR__ . '/../../../init.php');

//Find all users where first_name, last_name, or email are NULL
$users = new \SiteMaster\Core\Users\All();

//Send a request to directory for info
foreach ($users as $user) {
    if (!empty($user->first_name) && !empty($user->last_name) && !empty($user->email)) {
        //Don't need data.
        continue;
    }
    
    if ($user->provider != 'unl.edu') {
        continue;
    }
    
    if (!$json = @file_get_contents('http://directory.unl.edu/?format=json&uid=' . $user->uid)) {
        continue;
    }
    
    if (!$json = json_decode($json, true)) {
        continue;
    }
    
    echo 'updating: ' . $user->uid . PHP_EOL;
    
    if (empty($user->email) && isset($json['mail'][0])) {
        $user->email = $json['mail'][0];
    }

    if (empty($user->first_name) && isset($json['givenName'][0])) {
        $user->first_name = $json['givenName'][0];
    }

    if (empty($user->last_name) && isset($json['sn'][0])) {
        $user->last_name = $json['sn'][0];
    }
    
    $user->save();
    sleep(1);
}
