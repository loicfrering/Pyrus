--TEST--
PEAR2_Pyrus_Config::loadConfigFile() corrupt systemfile
--FILE--
<?php
require dirname(__FILE__) . '/setup.php.inc';
set_include_path(''); // disable include_path cascading for simplicity
file_put_contents($testpath . '/.config', '<?xml version="1.0" ?>oops> <cra&p>; ?>');
try {
    tc::$test = $test;
    $a = new tc($testpath, $testpath . '/blah');
    $test->assertEquals($testpath, $a->pearDir, 'peardir');
    $test->assertEquals($testpath . '/blah', $a->userFile, 'userfile');
} catch (Exception $e) {
    $test->assertException($e, 'PEAR2_Pyrus_Config_Exception', 'Unable to parse invalid PEAR configuration at "' . $testpath . '"', 'exception');
}
?>
===DONE===
--CLEAN--
<?php unlink(__DIR__ . '/testit/.config'); ?>
<?php unlink(__DIR__ . '/testit/.pear2registry'); ?>
<?php rmdir(__DIR__ . '/testit'); ?>
--EXPECT--
===DONE===