--TEST--
PEAR2_Pyrus_ChannelRegistry_Pear1::delete() readonly test
--FILE--
<?php
mkdir(__DIR__ . '/testit');
require dirname(dirname(__FILE__)) . '/../setup.php.inc';
// construct the registries first
$creg = new PEAR2_Pyrus_ChannelRegistry_Pear1(__DIR__ . '/testit', false);
$creg = new PEAR2_Pyrus_ChannelRegistry_Pear1(__DIR__ . '/testit', true);
$chan = new PEAR2_Pyrus_Channel(new PEAR2_Pyrus_ChannelFile(dirname(__DIR__).'/../sample_channel.xml'));
try {
    $creg->delete($chan);
    throw new Exception('passed and shouldn\'t');
} catch (PEAR2_Pyrus_ChannelRegistry_Exception $e) {
    $test->assertEquals('Cannot delete channel, registry is read-only', $e->getMessage(), 'message');
}
?>
===DONE===
--CLEAN--
<?php
$dir = __DIR__ . '/testit';
include __DIR__ . '/../../../clean.php.inc';
?>
--EXPECT--
===DONE===