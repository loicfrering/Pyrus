<?php
namespace pear2\Pyrus\ChannelRegistry;
class Foo extends Base
{
    public function add(\pear2\Pyrus\IChannel $channel, $update = false, $lastmodified = false){}
    public function update(\pear2\Pyrus\IChannel $channel){}
    public function delete(\pear2\Pyrus\IChannel $channel){}
    public function get($channel, $strict = true)
    {
        throw new Exception('buh');
    }
    public function exists($channel, $strict = true){}
    public function parseName($name){}
    public function parsedNameToString($name){}
    public function listChannels(){}
}