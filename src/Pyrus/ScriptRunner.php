<?php
/**
 * PEAR2_Pyrus_ScriptRunner
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
 * Post-install script runner for Pyrus
 *
 * This class handles the logic of navigating a post-install script's XML,
 * determining what questions to ask, and then passes the information to the
 * actual class.
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_ScriptRunner
{
    protected $frontend;

    function __construct($frontend)
    {
        $this->frontend = $frontend;
    }

    function run(PEAR2_Pyrus_IPackageFile $package)
    {
        foreach ($package->scriptfiles as $file) {
            
        }
    }
    /**
     * Instruct the runInstallScript method to skip a paramgroup that matches the
     * id value passed in.
     *
     * This method is useful for dynamically configuring which sections of a post-install script
     * will be run based on the user's setup, which is very useful for making flexible
     * post-install scripts without losing the cross-Frontend ability to retrieve user input
     * @param string
     */
    function skipParamgroup($id)
    {
        $this->_skipSections[$id] = true;
    }

    function runPostinstallScripts($scripts)
    {
        foreach ($scripts as $i => $script) {
            $this->runInstallScript($scripts[$i]->_params, $scripts[$i]->_obj);
        }
    }

    /**
     * @param PEAR2_Pyrus_Task_Postinstallscript $info contents of postinstallscript tag
     * @param object $script post-installation script
     * @param string install|upgrade
     */
    function runInstallScript(PEAR2_Pyrus_Task_Postinstallscript $info, $script)
    {
        $this->_skipSections = array();
        if (!count($info)) {
            $script->run(array(), '_default');
            return;
        }

        $completedPhases = array();

        try {
            foreach ($info->paramgroup as $group) {
                if (isset($this->_skipSections[$group->id])) {
                    // the post-install script chose to skip this section dynamically
                    continue;
                }
    
                if (isset($lastgroup)) {
                    if (!isset($answers)) {
                        $answers = null;
                    }
                    if (!$group->matchesConditionType($lastgroup, $answers)) {
                        continue;
                    }
                }
    
    
                $lastgroup = $group;
                if (isset($group->instructions)) {
                    $this->frontend->display($group->instructions);
                }
    
                if (isset($group->param)) {
                    if (method_exists($script, 'postProcessPrompts')) {
                        $prompts = $script->postProcessPrompts($group->param, $group->id);
                        if (!is_array($prompts) || count($prompts) != count($group->param)) {
                            throw new PEAR2_Pyrus_Task_Exception('Error: post-install script did not ' .
                                'return proper post-processed prompts');
                        } else {
                            foreach ($prompts as $i => $var) {
                                if (!is_array($var) || !isset($var['prompt']) ||
                                      !isset($var['name']) ||
                                      ($var['name'] != $group->param[$i]->name) ||
                                      ($var['type'] != $group->param[$i]->type)
                                ) {
                                    throw new PEAR2_Pyrus_Task_Exception('Error: post-install script ' .
                                        'modified the variables or prompts, severe security risk');
                                }
                            }
                        }
    
                        $answers = $this->frontend->confirmDialog($prompts);
                    } else {
                        $answers = $this->frontend->confirmDialog($group->param->getInfo());
                    }
                }
    
                if ((isset($answers) && $answers) || !isset($group->param)) {
                    if (!isset($answers)) {
                        $answers = array();
                    }
    
                    array_unshift($completedPhases, $group->id);
                    // script should throw an exception on failure
                    $script->run($answers, $group->id);
                } else {
                    $script->run($completedPhases, '_undoOnError');
                    return;
                }
            }
        } catch (\Exception $e) {
            $script->run($completedPhases, '_undoOnError');
            throw $e;
        }
    }
}
