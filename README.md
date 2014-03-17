UNL CAS Authentication Plugin for SiteMaster
==========================

To install, simply clone the repository into the sitemaster/plugins directory:

`git clone git@github.com:unl/sitemaster_plugin_auth_unl.git auth_unl`

Add a line in the `config.inc.php` to the PLUGINS config for the `auth_unl` plugin you just cloned.

`'auth_unl' => array(),`

Then, install the plugin by running `php scripts/install.php` which will install any dependencies and add
it to the list of installed plugins.

