<?php
/**
 * <tasks:postinstallscript>
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
 * Implements the postinstallscript file task.
 *
 * Note that post-install scripts are handled separately from installation, by the
 * "pear run-scripts" command
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_Task_Postinstallscript extends PEAR2_Pyrus_Task_Common
{
    var $type = 'script';
    var $_class;
    var $_params;
    var $_obj;
    /**
     *
     * @var PEAR_PackageFile_v2
     */
    var $_pkg;
    var $_contents;
    var $phase = PEAR2_PYRUS_TASK_INSTALL;

    /**
     * Validate the raw xml at parsing-time.
     *
     * This also attempts to validate the script to make sure it meets the criteria
     * for a post-install script
     * @param PEAR_PackageFile_v2
     * @param array The XML contents of the <postinstallscript> tag
     * @param PEAR_Config
     * @param array the entire parsed <file> tag
     * @static
     */
    function validateXml($pkg, $xml, $config, $fileXml)
    {
        if ($fileXml['role'] != 'php') {
            return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
            $fileXml['name'] . '" must be role="php"');
        }
        try {
            $file = $pkg->getFileContents($fileXml['name']);
        } catch (Exception $e) {
            return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                $fileXml['name'] . '" is not valid: ' .
                $e->getMessage());
        }
        if ($file === null) {
            return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                $fileXml['name'] . '" could not be retrieved for processing!');
        } else {
            $analysis = $pkg->analyzeSourceCode($file, true);
            if (!$analysis) {
                $warnings = '';
                foreach ($pkg->getValidationWarnings() as $warn) {
                    $warnings .= $warn['message'] . "\n";
                }
                return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Analysis of post-install script "' .
                    $fileXml['name'] . '" failed: ' . $warnings);
            }
            if (count($analysis['declared_classes']) != 1) {
                return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                    $fileXml['name'] . '" must declare exactly 1 class');
            }
            $class = $analysis['declared_classes'][0];
            if ($class != str_replace(array('/', '.php'), array('_', ''),
                  $fileXml['name']) . '_postinstall') {
                return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                    $fileXml['name'] . '" class "' . $class . '" must be named "' .
                    str_replace(array('/', '.php'), array('_', ''),
                    $fileXml['name']) . '_postinstall"');
            }
            if (!isset($analysis['declared_methods'][$class])) {
                return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                    $fileXml['name'] . '" must declare methods init() and run()');
            }
            $methods = array('init' => 0, 'run' => 1);
            foreach ($analysis['declared_methods'][$class] as $method) {
                if (isset($methods[$method])) {
                    unset($methods[$method]);
                }
            }
            if (count($methods)) {
                return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                    $fileXml['name'] . '" must declare methods init() and run()');
            }
        }
        $definedparams = array();
        $tasksNamespace = $pkg->getTasksNs() . ':';
        if (!isset($xml[$tasksNamespace . 'paramgroup']) && isset($xml['paramgroup'])) {
            // in order to support the older betas, which did not expect internal tags
            // to also use the namespace
            $tasksNamespace = '';
        }
        if (isset($xml[$tasksNamespace . 'paramgroup'])) {
            $params = $xml[$tasksNamespace . 'paramgroup'];
            if (!is_array($params) || !isset($params[0])) {
                $params = array($params);
            }
            foreach ($params as $param) {
                if (!isset($param[$tasksNamespace . 'id'])) {
                    return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                        $fileXml['name'] . '" <paramgroup> must have ' .
                        'an ' . $tasksNamespace . 'id> tag');
                }
                if (isset($param[$tasksNamespace . 'name'])) {
                    if (!in_array($param[$tasksNamespace . 'name'], $definedparams)) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" ' . $tasksNamespace .
                            'paramgroup> id "' . $param[$tasksNamespace . 'id'] .
                            '" parameter "' . $param[$tasksNamespace . 'name'] .
                            '" has not been previously defined');
                    }
                    if (!isset($param[$tasksNamespace . 'conditiontype'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" ' . $tasksNamespace .
                            'paramgroup> id "' . $param[$tasksNamespace . 'id'] .
                            '" must have a ' . $tasksNamespace .
                            'conditiontype> tag containing either "=", ' .
                            '"!=", or "preg_match"');
                    }
                    if (!in_array($param[$tasksNamespace . 'conditiontype'],
                          array('=', '!=', 'preg_match'))) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" ' . $tasksNamespace .
                            'paramgroup> id "' . $param[$tasksNamespace . 'id'] .
                            '" must have a ' . $tasksNamespace .
                            'conditiontype> tag containing either "=", ' .
                            '"!=", or "preg_match"');
                    }
                    if (!isset($param[$tasksNamespace . 'value'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" ' . $tasksNamespace .
                            'paramgroup> id "' . $param[$tasksNamespace . 'id'] .
                            '" must have a ' . $tasksNamespace .
                            'value> tag containing expected parameter value');
                    }
                }
                if (isset($param[$tasksNamespace . 'instructions'])) {
                    if (!is_string($param[$tasksNamespace . 'instructions'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" ' . $tasksNamespace .
                            'paramgroup> id "' . $param[$tasksNamespace . 'id'] .
                            '" ' . $tasksNamespace . 'instructions> must be simple text');
                    }
                }
                if (!isset($param[$tasksNamespace . 'param'])) {
                    continue; // <param> is no longer required
                }
                $subparams = $param[$tasksNamespace . 'param'];
                if (!is_array($subparams) || !isset($subparams[0])) {
                    $subparams = array($subparams);
                }
                foreach ($subparams as $subparam) {
                    if (!isset($subparam[$tasksNamespace . 'name'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" parameter for ' .
                            $tasksNamespace . 'paramgroup> id "' .
                            $param[$tasksNamespace . 'id'] . '" must have ' .
                            'a ' . $tasksNamespace . 'name> tag');
                    }
                    if (!preg_match('/[a-zA-Z0-9]+/',
                          $subparam[$tasksNamespace . 'name'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" parameter "' .
                            $subparam[$tasksNamespace . 'name'] .
                            '" for ' . $tasksNamespace . 'paramgroup> id "' .
                            $param[$tasksNamespace . 'id'] .
                            '" is not a valid name.  Must contain only alphanumeric characters');
                    }
                    if (!isset($subparam[$tasksNamespace . 'prompt'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" parameter "' .
                            $subparam[$tasksNamespace . 'name'] .
                            '" for ' . $tasksNamespace . 'paramgroup> id "' .
                            $param[$tasksNamespace . 'id'] .
                            '" must have a ' . $tasksNamespace . 'prompt> tag');
                    }
                    if (!isset($subparam[$tasksNamespace . 'type'])) {
                        return array(PEAR2_PYRUS_TASK_ERROR_INVALID, 'Post-install script "' .
                            $fileXml['name'] . '" parameter "' .
                            $subparam[$tasksNamespace . 'name'] .
                            '" for ' . $tasksNamespace . 'paramgroup> id "' .
                            $param[$tasksNamespace . 'id'] .
                            '" must have a ' . $tasksNamespace . 'type> tag');
                    }
                    $definedparams[] = $param[$tasksNamespace . 'id'] . '::' .
                    $subparam[$tasksNamespace . 'name'];
                }
            }
        }
        return true;
    }

    /**
     * Initialize a task instance with the parameters
     * @param array raw, parsed xml
     * @param array attributes from the <file> tag containing this task
     * @param string|null last installed version of this package, if any (useful for upgrades)
     */
    function init($xml, $fileattribs, $lastversion)
    {
        $this->_class = str_replace('/', '_', $fileattribs['name']);
        $this->_filename = $fileattribs['name'];
        $this->_class = str_replace ('.php', '', $this->_class) . '_postinstall';
        $this->_params = $xml;
        $this->_lastversion = $lastversion;
    }

    /**
     * Strip the tasks: namespace from internal params
     *
     * @access private
     */
    function _stripNamespace($params = null)
    {
        if ($params === null) {
            $params = array();
            if (!is_array($this->_params)) {
                return;
            }
            foreach ($this->_params as $i => $param) {
                if (is_array($param)) {
                    $param = $this->_stripNamespace($param);
                }
                $params[str_replace($this->_pkg->getTasksNs() . ':', '', $i)] = $param;
            }
            $this->_params = $params;
        } else {
            $newparams = array();
            foreach ($params as $i => $param) {
                if (is_array($param)) {
                    $param = $this->_stripNamespace($param);
                }
                $newparams[str_replace($this->_pkg->getTasksNs() . ':', '', $i)] = $param;
            }
            return $newparams;
        }
    }

    /**
     * Unlike other tasks, the installed file name is passed in instead of the file contents,
     * because this task is handled post-installation
     * @param PEAR_PackageFile_v1|PEAR_PackageFile_v2
     * @param string file name
     * @return bool|PEAR_Error false to skip this file, PEAR_Error to fail
     *         (use $this->throwError)
     */
    function startSession($pkg, $contents)
    {
        if ($this->installphase != PEAR2_PYRUS_TASK_INSTALL) {
            return false;
        }
        // remove the tasks: namespace if present
        $this->_pkg = $pkg;
        $this->_stripNamespace();
        PEAR2_Pyrus_Log::log(0, 'Including external post-installation script "' .
            $contents . '" - any errors are in this script');
        include $contents;
        if (class_exists($this->_class)) {
            PEAR2_Pyrus_Log::log(0, 'Inclusion succeeded');
        } else {
            throw new PEAR2_Pyrus_Task_Exception('init of post-install script class "' . $this->_class
                . '" failed');
        }
        $this->_obj = new $this->_class;
        PEAR2_Pyrus_Log::log(1, 'running post-install script "' . $this->_class . '->init()"');
        try {
            $res = $this->_obj->init($this->config, $pkg, $this->_lastversion);
        } catch (Exception $e) {
            throw new PEAR2_Pyrus_Task_Exception('init of post-install script "' . $this->_class .
                '->init()" failed');
        }
        PEAR2_Pyrus_Log::log(0, 'init succeeded');
        $this->_contents = $contents;
        return true;
    }

    /**
     * No longer used
     * @see PEAR_PackageFile_v2::runPostinstallScripts()
     * @param array an array of tasks
     * @param string install or upgrade
     * @access protected
     * @static
     */
    function run()
    {
    }
}
?>