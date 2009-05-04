--TEST--
PEAR2_Pyrus_ScriptFrontend_Commands::_findPEAR test 3: no userfile detected, decline creation
--FILE--
<?php
define('MYDIR', __DIR__);
require dirname(__DIR__) . '/setup.php.inc';
test_scriptfrontend::$stdin = array(
    'no', // answer to "It appears you have not used Pyrus before, welcome!  Initialize install?"
);
$cli = new test_scriptfrontend();
$cli->run($args = array ());

?>
===DONE===
--CLEAN--
<?php
$dir = __DIR__ . '/testit';
include __DIR__ . '/../../clean.php.inc';
?>
--EXPECT--
Pyrus: No user configuration file detected
It appears you have not used Pyrus before, welcome!  Initialize install?
Please choose:
  yes
  no
[yes] : OK, thank you, finishing execution now