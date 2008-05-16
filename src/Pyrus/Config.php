<?php
/**
 * PEAR2_Pyrus_Config
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
 * Pyrus's master configuration manager
 *
 * Unlike PEAR version 1.x, the new Pyrus configuration manager is tightly bound
 * to include_path, and will search through include_path for system configuration
 * Pyrus installations.
 *
 * The User configuration file will be looked for in these locations:
 *
 * Unix:
 *
 * - home directory
 * - current directory
 *
 * Windows:
 *
 * - local settings directory on windows for the current user.
 *   This is looked up directly in the windows registry using COM
 * - current directory
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_Config
{
    /**
     * location of PEAR2 installation
     *
     * @var string
     */
    protected $pearDir;
    /**
     * location of user-specific configuration file
     *
     * @var string
     */
    protected $userFile;
    /**
     * mapping of path => PEAR2 configuration objects
     *
     * @var array
     */
    static protected $configs = array();
    /**
     * The last instantiated configuration
     *
     * @var PEAR2_Pyrus_Config
     */
    static protected $current;
    /**
     * Default values for custom configuration values set by custom file roles.
     * @var array
     */
    static protected $customDefaults =
        array(
            );
    /**
     * Default values for configuration.
     *
     * @php_dir@ is automatically replaced with the current
     * PEAR2 configuration location
     * @var array
     */
    static protected $defaults =
        array(
            'php_dir' => '@php_dir@/src', // pseudo-value in this implementation
            'ext_dir' => '@php_dir@/ext_dir',
            'doc_dir' => '@php_dir@/docs',
            'bin_dir' => PHP_BINDIR,
            'data_dir' => '@php_dir@/data', // pseudo-value in this implementation
            'www_dir' => '@php_dir@/www',
            'test_dir' => '@php_dir@/tests',
            'php_bin' => '',
            'php_ini' => '',
            'default_channel' => 'pear2.php.net',
            'preferred_mirror' => 'pear2.php.net',
            'auto_discover' => 0,
            'http_proxy' => '',
            'cache_dir' => '@php_dir@/cache',
            'temp_dir' => '@php_dir@/temp',
            'download_dir' => '@php_dir@/downloads',
            'username' => '',
            'password' => '',
            'verbose' => 1,
            'preferred_state' => 'stable',
            'umask' => '0644',
            'cache_ttl' => 3600,
            'sig_type' => '',
            'sig_bin' => '',
            'sig_keyid' => '',
            'sig_keydir' => '',
            'my_pear_path' => '@php_dir@',
        );
    /**
     * Mapping of user configuration file path => config values
     *
     * @var array
     */
    static protected $userConfigs = array();
    /**
     * Configuration variable names that are bound to the PEAR installation
     *
     * These are values that should not change for different users
     * @var array
     */
    static protected $pearConfigNames = array(
            'php_dir', // pseudo-value in this implementation
            'ext_dir',
            'doc_dir',
            'bin_dir',
            'data_dir', // pseudo-value in this implementation
            'www_dir',
            'test_dir',
            'php_bin',
            'php_ini',
        );
    /**
     * Custom configuration variable names that are bound to the PEAR installation
     *
     * These are values that should not change for different users, and are
     * set by custom file roles
     * @var array
     */
    static protected $customPearConfigNames = array(
        );
    /**
     * Configuration variable names that are user-specific
     *
     * These are values that are user preferences rather than
     * information necessary for installation on the filesystem.
     * @var array
     */
    static protected $userConfigNames = array(
            'default_channel',
            'preferred_mirror',
            'auto_discover',
            'http_proxy',
            'cache_dir',
            'temp_dir',
            'download_dir',
            'username',
            'password',
            'verbose',
            'preferred_state',
            'umask',
            'cache_ttl',
            'sig_type',
            'sig_bin',
            'sig_keyid',
            'sig_keydir',
            'my_pear_path', // PATH_SEPARATOR-separated list of PEAR repositories to manage
        );
    /**
     * Configuration variable names that are user-specific
     *
     * These are values that are user preferences rather than
     * information necessary for installation on the filesystem, and
     * are set up by custom file roles
     * @var array
     */
    static protected $customUserConfigNames = array(
        );

    /**
     * Set up default configuration values that need to be determined at runtime
     *
     * The ext_dir variable, bin_dir variable, and php_ini are set up in
     * this method.
     */
    protected static function constructDefaults()
    {
        static $called = false;
        if ($called) {
            return;
        }
        $called = true;
        // set up default ext_dir
        if (getenv('PHP_PEAR_EXTENSION_DIR')) {
            self::$defaults['ext_dir'] = getenv('PHP_PEAR_EXTENSION_DIR');
            PEAR2_Pyrus_Log::log(5, 'used PHP_PEAR_EXTENSION_DIR environment variable');
        } elseif (ini_get('extension_dir')) {
            self::$defaults['ext_dir'] = ini_get('extension_dir');
            PEAR2_Pyrus_Log::log(5, 'used ini_get(extension_dir)');
        } elseif (defined('PEAR_EXTENSION_DIR')) {
            self::$defaults['ext_dir'] = PEAR_EXTENSION_DIR;
            PEAR2_Pyrus_Log::log(5, 'used PEAR_EXTENSION_DIR constant');
        }
        // set up default bin_dir
        if (getenv('PHP_PEAR_BIN_DIR')) {
            self::$defaults['bin_dir'] = getenv('PHP_PEAR_BIN_DIR');
            PEAR2_Pyrus_Log::log(5, 'used PHP_PEAR_BIN_DIR environment variable');
        } elseif (PATH_SEPARATOR == ';') {
            // we're on windows, and shouldn't use PHP_BINDIR
            do {
                if (!isset($_ENV) || !isset($_ENV['PATH'])) {
                    $path = getenv('PATH');
                } else {
                    $path = $_ENV['PATH'];
                }
                if (!$path) {
                    PEAR2_Pyrus_Log::log(5, 'used PHP_BINDIR on windows for bin_dir default');
                    break; // can't get PATH, so use PHP_BINDIR
                }
                $paths = explode(';', $path);
                foreach ($paths as $path) {
                    if ($path != '.' && is_writable($path)) {
                        // this place will do
                        PEAR2_Pyrus_Log::log(5, 'used ' . $path . ' for default bin_dir');
                        self::$defaults['bin_dir'] = $path;
                    }
                }
            } while (false);
        } else {
            PEAR2_Pyrus_Log::log(5, 'used PHP_BINDIR for bin_dir default');
        }
        foreach (self::$pearConfigNames as $name) {
            // make sure we've got valid paths for the underlying OS
            self::$defaults[$name] = str_replace('/', DIRECTORY_SEPARATOR,
                                                 self::$defaults[$name]);
        }
        foreach (self::$userConfigNames as $name) {
            // make sure we've got valid paths for the underlying OS
            self::$defaults[$name] = str_replace('/', DIRECTORY_SEPARATOR,
                                                 self::$defaults[$name]);
        }
        self::$defaults['php_ini'] = php_ini_loaded_file();
        if (self::$defaults['php_ini']) {
            PEAR2_Pyrus_Log::log(5, 'Used ' . self::$defaults['php_ini'] . ' for php.ini location');
        } else {
            PEAR2_Pyrus_Log::log(5, 'Could not find php.ini');
        }
    }

    /**
     * parse a configuration for a PEAR2 installation
     *
     * @param string $pearDirectory
     * @param string $userfile
     */
    protected function __construct($pearDirectory, $userfile = false)
    {
        $pearDirectory = str_replace('\\', '/', $pearDirectory);
        $pearDirectory = str_replace('//', '/', $pearDirectory);
        $pearDirectory = str_replace('/', DIRECTORY_SEPARATOR, $pearDirectory);
        $pearDirectory = $this->setCascadingRegistries($pearDirectory);
        self::constructDefaults();
        $this->loadConfigFile($pearDirectory, $userfile);
        $this->pearDir = $pearDirectory;
        self::$configs[$pearDirectory] = $this;
        if (!isset(self::$current)) {
            self::$current = $this;
        }
    }

    /**
     * Retrieve configuration for a PEAR2 installation
     *
     * @param string $pearDirectory
     * @param string $userfile
     * @return PEAR2_Pyrus_Config
     */
    static public function singleton($pearDirectory, $userfile = false)
    {
        if (isset(self::$configs[$pearDirectory])) {
            return self::$configs[$pearDirectory];
        }
        self::$configs[$pearDirectory] = new PEAR2_Pyrus_Config($pearDirectory, $userfile);
        return self::$configs[$pearDirectory];
    }

    static public function setCascadingRegistries($path)
    {
        $paths = explode(PATH_SEPARATOR, $path);
        $ret = $paths[0];
        if (count($paths) == 1) {
            // add registries within include_path by default
            // if explicit path is specified, user knows what they
            // are doing, don't add include_path
            PEAR2_Pyrus_Log::log(1, 'Automatically cascading include_path');
            $extra = explode(PATH_SEPARATOR, get_include_path());
            array_unshift($extra, $ret);
            $paths = $extra;
        }
        foreach ($paths as $path) {
            if ($path === '.') continue;
            $reg = PEAR2_Pyrus_Registry::singleton($path);
            $regc = PEAR2_Pyrus_ChannelRegistry::singleton($path);
            if (isset($last)) {
                $last->setParent($reg);
                $lastc->setParent($regc);
            }
            $last = $reg;
            $lastc = $regc;
        }
        return $ret;
    }

    /**
     * Retrieve the currently active primary configuration
     * @return PEAR2_Pyrus_Config
     */
    static public function current()
    {
        if (isset(self::$current)) {
            return self::$current;
        }
        // default
        return PEAR2_Pyrus_Config::singleton(getcwd());
    }

    /**
     * determines where user-specific configuration files should be saved.
     *
     * On unix, this is ~user/ or a location in /tmp based on the current directory.
     * On windows, this is your Documents and Settings folder.
     * @return string
     */
    protected function locateLocalSettingsDirectory()
    {
        if (class_exists('COM', false)) {
            // windows, grab current user My Documents folder
            $info = new COM('winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2');
            $users = $info->ExecQuery("Select * From Win32_ComputerSystem");
            foreach ($users as $user) {
                $d = explode('\\', $user->UserName);
                $curuser = $d[1];
            }
            $registry = new COM('Wscript.Shell');
            return $registry->RegRead(
                'HKLM\\Software\\Microsoft\\Windows\\CurrentVersion\\' .
                'Explorer\\DocFolderPaths\\' . $curuser);
        } else {
            if (isset($_ENV['HOME'])) {
                return $_ENV['HOME'];
            } elseif ($e = getenv('HOME')) {
                return $e;
            } else {
                return '/tmp/' . md5($_ENV['PWD']);
            }
        }
    }

    /**
     * Extract configuration from system + user configuration files
     *
     * Configuration is stored in XML format, in two locations.
     *
     * The system configuration contains all of the important directory
     * configuration variables like data_dir, and the location of php.ini and
     * the php executable php.exe or php.  This configuration is tightly bound
     * to the repository, and cannot be moved.  As such, php_dir is auto-defined
     * as dirname(/path/to/pear/.config), or /path/to/pear.
     *
     * Only 1 user configuration file is allowed, and contains user-specific
     * settings, including the locations where to download package releases
     * and where to cache files downloaded from the internet.  If false is passed
     * in, PEAR2_Pyrus_Config will attempt to guess at the config file location as
     * documented in the class docblock {@link PEAR2_Pyrus_Config}.
     * @param string $pearDirectory
     * @param string|false $userfile
     */
    protected function loadConfigFile($pearDirectory, $userfile = false)
    {
        if (!isset(self::$configs[$pearDirectory]) &&
              file_exists($pearDirectory . DIRECTORY_SEPARATOR . '.config')) {
            PEAR2_Pyrus_Log::log(5, 'Loading configuration for ' . $pearDirectory);
            libxml_use_internal_errors(true);
            libxml_clear_errors();
            $x = simplexml_load_file($pearDirectory . DIRECTORY_SEPARATOR . '.config');
            if (!$x) {
                $errors = libxml_get_errors();
                $e = new PEAR2_MultiErrors;
                foreach ($errors as $err) {
                    $e->E_ERROR[] = new PEAR2_Pyrus_Config_Exception(trim($err->message));
                }
                libxml_clear_errors();
                throw new PEAR2_Pyrus_Config_Exception(
                    'Unable to parse invalid PEAR configuration at "' . $pearDirectory . '"',
                    $e);
            }
            $unsetvalues = array_diff(array_keys((array) $x), array_merge(self::$pearConfigNames, self::$customPearConfigNames));
            // remove values that are not recognized system config variables
            foreach ($unsetvalues as $value)
            {
                if ($value == '@attributes') {
                    continue;
                }
                if ($var === 'php_dir' || $var === 'data_dir') {
                    unset($x->$value); // both of these are abstract
                }
                PEAR2_Pyrus_Log::log(5, 'Removing unrecognized configuration value ' .
                    $value);
                unset($x->$value);
            }
            self::$configs[$pearDirectory] = $x;
        } else {
            PEAR2_Pyrus_Log::log(5, 'Configuration not found for ' . $pearDirectory .
                ', assuming defaults');
        }
        if (!$userfile) {
            if (class_exists('COM', false)) {
                $userfile = $this->locateLocalSettingsDirectory() . DIRECTORY_SEPARATOR .
                    'pear' . DIRECTORY_SEPARATOR . 'pearconfig.xml';
            } else {
                $userfile = $this->locateLocalSettingsDirectory() . DIRECTORY_SEPARATOR .
                    '.pear' . DIRECTORY_SEPARATOR . 'pearconfig.xml';
            }
            if (!file_exists($userfile)) {
                $test = realpath(getcwd() . DIRECTORY_SEPARATOR . 'pearconfig.xml');
                if ($test && file_exists($test)) {
                    PEAR2_Pyrus_Log::log(5, 'Found user configuration file in current directory' .
                        $userfile);
                    $userfile = $test;
                }
            } else {
                PEAR2_Pyrus_Log::log(5, 'Found default user configuration file ' .
                    $userfile);
            }
        } else {
            PEAR2_Pyrus_Log::log(5, 'Using explicit user configuration file ' . $userfile);
        }
        $this->userFile = $userfile;
        if (!$userfile || !file_exists($userfile)) {
            PEAR2_Pyrus_Log::log(5, 'User configuration file ' . $userfile . ' not found');
            return;
        }
        if (isset(self::$userConfigs[$userfile])) {
            return;
        }
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $x = simplexml_load_file($userfile);
        if (!$x) {
            $errors = libxml_get_errors();
            $e = new PEAR2_MultiErrors;
            foreach ($errors as $err) {
                $e->E_ERROR[] = new PEAR2_Pyrus_Config_Exception(trim($err->message));
            }
            libxml_clear_errors();
            throw new PEAR2_Pyrus_Config_Exception(
                'Unable to parse invalid user PEAR configuration at "' . $userfile . '"',
                $e);
        }
        $unsetvalues = array_diff(array_keys((array) $x), array_merge(self::$userConfigNames, self::$customUserConfigNames));
        // remove values that are not recognized user config variables
        foreach ($unsetvalues as $value)
        {
            if ($value == '@attributes') {
                continue;
            }
            PEAR2_Pyrus_Log::log(5, 'Removing unrecognized user configuration value ' .
                $value);
            unset($x->$value);
        }
        if (!$x->my_pear_path) {
            $x->my_pear_path = $pearDirectory;
            PEAR2_Pyrus_Log::log(5, 'Assuming my_pear_path is ' . $pearDirectory);
        } else {
            $this->setCascadingRegistries((string)$x->my_pear_path);
        }
        self::$userConfigs[$userfile] = $x;
    }

    /**
     * Save both the user configuration file and the system file
     *
     * If the userfile is not passed in, it is saved in the default
     * location which is either in ~/.pear/pearconfig.xml or on Windows
     * in the Documents and Settings directory
     * @param string $userfile path to alternate user configuration file
     */
    function saveConfig($userfile = false)
    {
        if (!$userfile) {
            if ($this->userFile) {
                $userfile = $this->userFile;
            } else {
                if (class_exists('COM', false)) {
                    $userfile = $this->locateLocalSettingsDirectory() . DIRECTORY_SEPARATOR .
                        'pear' . DIRECTORY_SEPARATOR . 'pearconfig.xml';
                } else {
                    $userfile = $this->locateLocalSettingsDirectory() . DIRECTORY_SEPARATOR .
                        '.pear' . DIRECTORY_SEPARATOR . 'pearconfig.xml';
                }
            }
        }
        $userfile = str_replace('\\', '/', $userfile);
        $userfile = str_replace('//', '/', $userfile);
        $userfile = str_replace('/', DIRECTORY_SEPARATOR, $userfile);
        $test = $userfile;
        while ($test && !file_exists($test)) {
            $test = dirname($test);
        }
        if (!is_writable($test)) {
            throw new PEAR2_Pyrus_Config_Exception('Cannot save configuration, no' .
                ' filesystem permissions to modify user configuration file ' . $userfile);
        }
        $test = $this->pearDir . '.config';
        while ($test && !file_exists($test)) {
            $test = dirname($test);
        }
        if (!is_writable($test)) {
            throw new PEAR2_Pyrus_Config_Exception('Cannot save configuration, no' .
                ' filesystem permissions to modify PEAR directory ' . $this->pearDir . '.config');
        }
        $x = simplexml_load_string('<pearconfig version="1.0"></pearconfig>');
        foreach (self::$userConfigNames as $var) {
            $x->$var = (string) $this->$var;
        }
        foreach (self::$customUserConfigNames as $var) {
            $x->$var = (string) $this->$var;
        }
        if (!file_exists(dirname($userfile))) {
            if (!@mkdir(dirname($userfile), 0777, true)) {
                throw new PEAR2_Pyrus_Config_Exception(
                    'Unable to create directory ' . dirname($userfile) . ' to save ' .
                    'user configuration ' . $userfile);
            }
        }
        file_put_contents($userfile, $x->asXML());

        $system = $this->pearDir . '.config';
        if (dirname($system) != $this->pearDir) {
            $system = $this->pearDir . DIRECTORY_SEPARATOR . '.config';
        }
        if (!file_exists(dirname($system))) {
            if (!@mkdir(dirname($system), 0777, true)) {
                throw new PEAR2_Pyrus_Config_Exception(
                    'Unable to create directory ' . dirname($system) . ' to save ' .
                    'system configuration ' . $system);
            }
        }
        $x = simplexml_load_string('<pearconfig version="1.0"></pearconfig>');
        foreach (self::$pearConfigNames as $var) {
            if ($var === 'php_dir' || $var === 'data_dir') {
                continue; // both of these are abstract
            }
            $x->$var = $this->$var;
            file_put_contents(dirname($system) . DIRECTORY_SEPARATOR .
                $var . '.txt', $this->$var);
        }
        foreach (self::$customPearConfigNames as $var) {
            $x->$var = $this->$var;
            file_put_contents(dirname($system) . DIRECTORY_SEPARATOR .
                $var . '.txt', $this->$var);
        }
        file_put_contents($system, $x->asXML());
    }

    /**
     * Save a snapshot of the current config, and return the file name
     *
     * If the latest snapshot is the same as the existing configuration,
     * simply return the filename
     * @return string basename of the snapshot file of the current configuration
     */
    static public function configSnapshot()
    {
        $conf = self::current();
        $snapshotdir = $conf->pearDir . DIRECTORY_SEPARATOR . '.configsnapshots';
        if (!file_exists($snapshotdir)) {
            // this will be simple - no snapshots exist yet
            if (!@mkdir($snapshotdir, 0755, true)) {
                throw new PEAR2_Pyrus_Config_Exception(
                    'Unable to create directory ' . $snapshotdir . ' to save ' .
                    'system configuration snapshots');
            }
            $snapshot = 'configsnapshot-' . date('Ymd') . '.xml';
            $x = simplexml_load_string('<pearconfig version="1.0"></pearconfig>');
            foreach (self::$pearConfigNames as $var) {
                $x->$var = $conf->$var;
            }
            foreach (self::$customPearConfigNames as $var) {
                $x->$var = $conf->$var;
            }
            PEAR2_Pyrus_Log::log(5, 'Saving configuration snapshot ' . $snapshot);
            file_put_contents($snapshotdir . DIRECTORY_SEPARATOR . $snapshot, $x->asXML());
            return $snapshot;
        }
        // scan existing snapshots, if any, for a match
        $dir = opendir($snapshotdir);
        while (false !== ($snapshot = readdir($dir))) {
            if ($snapshot[0] == '.') continue;
            $x = simplexml_load_file($snapshotdir . DIRECTORY_SEPARATOR . $snapshot);
            foreach (self::$pearConfigNames as $var) {
                if ($x->$var != $conf->$var) continue 2;
            }
            foreach (self::$customPearConfigNames as $var) {
                if (!isset($x->var) || $x->$var != $conf->$var) continue 2;
            }
            // found a match
            PEAR2_Pyrus_Log::log(5, 'Found matching configuration snapshot ' . $snapshot);
            return $snapshot;
        }
        PEAR2_Pyrus_Log::log(5, 'No matching configuration snapshot found');
        // no matches found
        $snapshot = 'configsnapshot-' . date('Ymd') . '.xml';
        $i = 0;
        while (file_exists($snapshotdir . DIRECTORY_SEPARATOR . $snapshot)) {
            $i++;
            // keep appending ".1" until we get a unique filename
            $snapshot = 'configsnapshot-' . date('Ymd') . str_repeat('.1', $i) . '.xml';
        }
        // save the snapshot
        $x = simplexml_load_string('<pearconfig version="1.0"></pearconfig>');
        foreach (self::$pearConfigNames as $var) {
            $x->$var = $conf->$var;
        }
        foreach (self::$customPearConfigNames as $var) {
            $x->$var = $conf->$var;
        }
        PEAR2_Pyrus_Log::log(5, 'Saving configuration snapshot ' . $snapshot);
        file_put_contents($snapshotdir . DIRECTORY_SEPARATOR . $snapshot, $x->asXML());
        return $snapshot;
    }

    /**
     * Load a configuration
     */
    static public function addConfigValue($key, $default, $system = true)
    {
        if (!preg_match('/^[a-z0-9-_]+\\z/', $key)) {
            throw new PEAR2_Pyrus_Config_Exception('Invalid custom configuration name "'.  $key . '"');
        }
        if ($system) {
            $var = 'customPearConfigNames';
        } else {
            $var = 'customUserConfigNames';
        }
        self::$$var[count(self::$$var)] = $key;
        self::$customDefaults[$key] = $default;
    }

    public function __get($value)
    {
        if ($value == 'registry') {
            return PEAR2_Pyrus_Registry::singleton($this->pearDir);
        }
        if ($value == 'channelregistry') {
            return PEAR2_Pyrus_ChannelRegistry::singleton($this->pearDir);
        }
        if ($value == 'systemvars') {
            return array_merge(self::$pearConfigNames, self::$customPearConfigNames);
        }
        if ($value == 'uservars') {
            return array_merge(self::$userConfigNames, self::$customUserConfigNames);
        }
        if ($value == 'mainsystemvars') {
            return self::$pearConfigNames;
        }
        if ($value == 'mainuservars') {
            return self::$userConfigNames;
        }
        if ($value == 'userfile') {
            return $this->userFile;
        }
        if ($value == 'path') {
            return $this->pearDir;
        }
        if (!in_array($value, array_merge(self::$pearConfigNames, self::$userConfigNames,
                                          self::$customPearConfigNames,
                                          self::$customUserConfigNames))) {
            throw new PEAR2_Pyrus_Config_Exception(
                'Unknown configuration variable "' . $value . '" in location ' .
                $this->pearDir);
        }
        if (!isset($this->$value)) {
            if (isset(self::$defaults[$value])) {
                PEAR2_Pyrus_Log::log(5, 'Replacing @php_dir@ for config variable ' . $value .
                    ' default value "' . self::$defaults[$value] . '"');
                return str_replace('@php_dir@', $this->pearDir, self::$defaults[$value]);
            } else {
                PEAR2_Pyrus_Log::log(5, 'Replacing @php_dir@ for config variable ' . $value .
                    ' default value "' . self::$customDefaults[$value] . '"');
                return str_replace('@php_dir@', $this->pearDir, self::$customDefaults[$value]);
            }
        }
        if (in_array($value, array_merge(self::$pearConfigNames, self::$customPearConfigNames))) {
            PEAR2_Pyrus_Log::log(5, 'Replacing @php_dir@ for config variable ' . $value .
                ' value "' . self::$configs[$this->pearDir]->$value . '"');
            return (string) str_replace('@php_dir@', $this->pearDir,
                self::$configs[$this->pearDir]->$value);
        }
        return (string) self::$userConfigs[$this->userFile]->$value;
    }

    public function __isset($value)
    {
        if ($value === 'php_dir' || $value === 'data_dir') {
            return true;
        }
        if (in_array($value, self::$pearConfigNames)
            || in_array($value, self::$customPearConfigNames)) {
            return isset(self::$configs[$this->pearDir]->$value);
        }
        return isset(self::$userConfigs[$this->userFile]->$value);
    }

    public function __set($key, $value)
    {
        if ($key == 'php_dir' || $key == 'data_dir') {
            throw new PEAR2_Pyrus_Config_Exception('Cannot set php_dir, move the repository');
        }
        if (!isset(self::$defaults[$key]) && !isset(self::$customDefaults[$key])) {
            throw new PEAR2_Pyrus_Config_Exception(
                'Unknown configuration variable "' . $key . '" in location ' .
                $this->pearDir);
        }
        if (in_array($key, self::$pearConfigNames)
            || in_array($key, self::$customPearConfigNames)) {
            // global config
            self::$configs[$this->pearDir]->$key = $value;
        } else {
            // local config
            self::$userConfigs[$this->userFile]->$key = $value;
        }
    }
}