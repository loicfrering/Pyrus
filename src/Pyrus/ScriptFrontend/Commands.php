<?php
/**
 * PEAR2_Pyrus_ScriptFrontend_Commands
 *
 * PHP version 5
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   SVN: $Id$
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */

/**
 * PEAR2_Pyrus_ScriptFrontend_Commands
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_ScriptFrontend_Commands
{
    public $commands = array();

    function __construct()
    {
        $a = new ReflectionClass($this);
        foreach ($a->getMethods() as $method) {
            $name = $method->getName();
            if ($name[0] == '_' || $name === 'run') {
                continue;
            }
            $this->commands[preg_replace_callback('/[A-Z]/',
                    create_function('$m', 'return "-" . strtolower($m[0]);'), $name)] = $name;
        }
    }

    function run($args)
    {
        try {
            if (!count($args)) {
                $args[0] = 'help';
            }
            $this->_findPEAR($args);
            if (isset($this->commands[$args[0]])) {
                $command = array_shift($args);
                $command = $this->commands[$command];
                $this->$command($args);
            } else {
                $this->help($args);
            }
        } catch (Exception $e) {
            echo "Operation failed:\n$e";
        }
    }

    function _findPEAR(&$arr)
    {
        if (isset($arr[0]) && @file_exists($arr[0]) && @is_dir($arr[0])) {
            $maybe = array_shift($arr);
            $maybe = realpath($maybe);
            echo "Using PEAR installation found at $maybe\n";
            $config = PEAR2_Pyrus_Config::singleton($maybe);
            return;
        }
        $mypath = PEAR2_Pyrus_Config::singleton()->my_pear_path;
        if ($mypath) {
            foreach (explode(PATH_SEPARATOR, $mypath) as $path) {
                echo "Using PEAR installation found at $path\n";
                $config = PEAR2_Pyrus_Config::singleton($path);
                return;
            }
        }
        $include_path = explode(PATH_SEPARATOR, get_include_path());
        foreach ($include_path as $path) {
            if ($path == '.') continue;
            echo "Using PEAR installation found at $path\n";
            $config = PEAR2_Pyrus_Config::singleton($path);
            return;
        }
        echo "Using PEAR installation in current directory\n";
    }

    function help($args)
    {
        if (isset($args[0]) && $args[0] == 'help') {
            echo "Commands supported:\n";
            foreach ($this->commands as $command => $true) {
                echo "$command\n";
            }
        } else {
            if (isset($args[0])) {
                echo "Unknown command: $args[0]\n";
            }
            echo "Commands supported:\n";
            foreach ($this->commands as $command => $true) {
                echo "$command [PEARPath]\n";
            }
        }
    }

    function install($args)
    {
        PEAR2_Pyrus_Installer::begin();
        try {
            $packages = array();
            foreach ($args as $arg) {
                PEAR2_Pyrus_Installer::prepare($packages[] = new PEAR2_Pyrus_Package($arg));
            }
            PEAR2_Pyrus_Installer::commit();
            foreach ($packages as $package) {
                echo 'Installed ' . $package->channel . '\\' . $package->name . '-' . $package->version['release'] . "\n";
            }
        } catch (Exception $e) {
            echo $e;
            exit -1;
        }
    }

    function download($args)
    {
        PEAR2_Pyrus_Config::current()->download_dir = getcwd();
        $packages = array();
        foreach ($args as $arg) {
            try {
                $packages[] = array(new PEAR2_Pyrus_Package($arg), $arg);
            } catch (Exception $e) {
                echo "failed to init $arg for download (", $e->getMessage(), ")\n";
            }
        }
        foreach ($packages as $package) {
            $arg = $package[1];
            $package = $package[0];
            echo "Downloading ", $arg, '...';
            try {
                $package->download();
                $path = $package->getInternalPackage()->getTarballPath();
                echo "done ($path)\n";
            } catch (Exception $e) {
                echo 'failed! (', $e->getMessage(), ")\n";
            }
        }
    }

    function upgrade($args)
    {
        PEAR2_Pyrus_Installer::$options['upgrade'] = true;
        $this->install($args);
    }

    function listPackages($args)
    {
        $reg = PEAR2_Pyrus_Config::current()->registry;
        $creg = PEAR2_Pyrus_Config::current()->channelregistry;
        $cascade = array(array($reg, $creg));
        while ($p = $reg->getParent() && $c = $creg->getParent()) {
            $cascade[] = array($p, $c);
        }
        array_reverse($cascade);
        foreach ($cascade as $p) {
            $c = $p[1];
            $p = $p[0];
            echo "Listing installed packages [", $p->getPath(), "]:\n";
            $packages = array();
            foreach ($c as $channel) {
                PEAR2_Pyrus_Config::current()->default_channel = $channel->name;
                foreach ($p as $package) {
                    $packages[$channel->name][] = $package->name;
                }
            }
            asort($packages);
            foreach ($packages as $channel => $stuff) {
                echo "[channel $channel]:\n";
                foreach ($stuff as $package) {
                    echo " $package\n";
                }
            }
        }
    }

    function listChannels($args)
    {
        $creg = PEAR2_Pyrus_Config::current()->channelregistry;
        $cascade = array($creg);
        while ($c = $creg->getParent()) {
            $cascade[] = $c;
        }
        array_reverse($cascade);
        foreach ($cascade as $c) {
            echo "Listing channels [", $c->getPath(), "]:\n";
            foreach ($c as $channel) {
                echo $channel->getName() . ' (' . $channel->getAlias() . ")\n";
            }
        }
    }

    function channelDiscover($args)
    {
        $chan = 'http://' . $args[0] . '/channel.xml';
        $http = new PEAR2_HTTP_Request($chan);
        try {
            $response = $http->sendRequest();
        } catch (Exception $e) {
            // try secure
            try {
                $chan = 'https://' . $args[0] . '/channel.xml';
                $http = new PEAR2_HTTP_Request($chan);
                $response = $http->sendRequest();
            } catch (Exception $u) {
                // failed, re-throw original error
                throw $e;
            }
        }

        $chan = new PEAR2_Pyrus_Channel($response->body);
        PEAR2_Pyrus_Config::current()->channelregistry->add($chan);
        echo "Discovery of channel ", $chan->name, " successful\n";
    }

    function channelAdd($args)
    {
        echo "Adding channel from channel.xml:\n";
        $chan = file_get_contents($args[0]);
        if (!$chan) {
            echo "Retrieving channel.xml contents failed\n";
            exit -1;
        }
        $chan = new PEAR2_Pyrus_Channel($chan);
        PEAR2_Pyrus_Config::current()->channelregistry->add($chan);
        echo "Adding channel ", $chan->name, " successful\n";
    }

    function channelDel($args)
    {
        echo "Adding channel from channel.xml:\n";
        $chan = PEAR2_Pyrus_Config::current()->channelregistry->get($args[0], false);
        if (count($chan)) {
            echo "Cannot remove channel ", $chan->name, "packages are installed\n";
            exit -1;
        }
        PEAR2_Pyrus_Config::current()->channelregistry->delete($chan);
        echo "Deleting channel ", $chan->name, " successful\n";
    }

    function configShow($args)
    {
        $conf = PEAR2_Pyrus_Config::current();
        echo "System paths:\n";
        foreach ($conf->mainsystemvars as $var) {
            echo "  $var => " . $conf->$var . "\n";
        }
        echo "Custom System paths:\n";
        foreach ($conf->customsystemvars as $var) {
            echo "  $var => " . $conf->$var . "\n";
        }
        echo "User config (from " . $conf->userfile . "):\n";
        foreach ($conf->mainuservars as $var) {
            echo "  $var => " . $conf->$var . "\n";
        }
        echo "Custom User config (from " . $conf->userfile . "):\n";
        foreach ($conf->customuservars as $var) {
            echo "  $var => " . $conf->$var . "\n";
        }
    }

    function set($args)
    {
        $conf = PEAR2_Pyrus_Config::current();
        if (in_array($args[0], $conf->uservars)) {
            echo "Setting $args[0] in " . $conf->userfile . "\n";
            $conf->{$args[0]} = $args[1];
        } elseif (in_array($args[0], $conf->systemvars)) {
            echo "Setting $args[0] in system paths\n";
            $conf->{$args[0]} = $args[1];
        } else {
            echo "Unknown config variable: $args[0]\n";
            exit -1;
        }
        $conf->saveConfig();
    }

    function mypear($args)
    {
        echo "Setting my pear repositories to:\n";
        echo implode("\n", $args) . "\n";
        $args = implode(PATH_SEPARATOR, $args);
        PEAR2_Pyrus_Config::current()->my_pear_path = $args;
        PEAR2_Pyrus_Config::current()->saveConfig();
    }
}
