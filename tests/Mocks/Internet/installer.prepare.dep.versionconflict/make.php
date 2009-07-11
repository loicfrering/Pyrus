<?php
/**
 * Create a dependency tree like so:
 *
 * P1 -> P2 >= 1.2.0 (1.2.3 is latest version)
 *
 * P2 1.2.3 -> P3
 *          -> P5
 *
 * P2 1.2.2 -> P3
 *
 * P3
 *
 * P4 -> P2 != 1.2.3
 *
 * P5
 *
 * This causes a conflict when P1 and P4 are installed that must resolve to installing:
 *
 * P1
 * P2 1.2.2
 * P3
 * P4
 */

require __DIR__ . '/../../../../../autoload.php';

set_include_path(__DIR__);
$c = \pear2\Pyrus\Config::singleton(dirname(__DIR__), dirname(__DIR__) . '/pearconfig.xml');
$c->bin_dir = __DIR__ . '/bin';
restore_include_path();
$c->saveConfig();

$chan = new pear2\SimpleChannelServer\Channel('pear2.php.net', 'unit test channel');
$scs = new pear2\SimpleChannelServer\Main($chan, __DIR__, dirname(__DIR__) . '/PEAR2');

$scs->saveChannel();

$pf = new \pear2\Pyrus\PackageFile\v2;

for ($i = 1; $i <= 5; $i++) {
    file_put_contents(__DIR__ . "/glooby$i", 'hi');
}

$pf->name = 'P1';
$pf->channel = 'pear2.php.net';
$pf->summary = 'testing';
$pf->version['release'] = '1.0.0';
$pf->stability['release'] = 'stable';
$pf->description = 'hi description';
$pf->notes = 'my notes';
$pf->maintainer['cellog']->role('lead')->email('cellog@php.net')->active('yes')->name('Greg Beaver');

$pf->setPackagefile(__DIR__ . '/package.xml');
$save = clone $pf;

$pf->dependencies['required']->package['pear2.php.net/P2']->min('1.2.0');
$pf->files['glooby1'] =  array('role' => 'php');

$p2_3 = clone $save;
$p2_3->name = 'P2';
$p2_3->version['release'] = '1.2.3';
$p2_3->dependencies['required']->package['pear2.php.net/P3']->save();
$p2_3->dependencies['required']->package['pear2.php.net/P5']->save();
$p2_3->files['glooby2'] =  array('role' => 'php');

$p2_2 = clone $save;
$p2_2->name = 'P2';
$p2_2->version['release'] = '1.2.2';
$p2_2->dependencies['required']->package['pear2.php.net/P3']->save();
$p2_2->files['glooby2'] =  array('role' => 'php');


$p2_0 = clone $save;
$p2_0->name = 'P2';
$p2_0->version['release'] = '0.9.0';
$p2_0->stability['release'] = 'beta';
$p2_0->files['glooby2'] =  array('role' => 'php');

$p3 = clone $save;
$p3->name = 'P3';
$p3->files['glooby3'] =  array('role' => 'php');

$p4 = clone $save;
$p4->name = 'P4';
$p4->dependencies['required']->package['pear2.php.net/P2']->min('1.2.0')->exclude('1.2.3');
$p4->files['glooby4'] =  array('role' => 'php');

$p5 = $save;
$p5->name = 'P5';
$p5->stability['release'] = 'beta';
$p5->version['release'] = '0.9.0';
$p5->files['glooby5'] =  array('role' => 'php');

file_put_contents(__DIR__ . '/package.xml', $pf);

$cat = pear2\SimpleChannelServer\Categories::create('Category 1', 'First Category')
                                           ->create('Category 2', 'Second Category');

$cat->link('P1', 'Category 1');
$cat->link('P2', 'Category 2');
$cat->link('P3', 'Category 1');
$cat->link('P4', 'Category 2');
$cat->link('P5', 'Category 1');

$package1 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($pf);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package1, $xmlcontainer);
$package1->setInternalPackage($xml);
$package1->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package1, 'cellog');

$package2_0_9_0 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p2_0);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package2_0_9_0, $xmlcontainer);
$package2_0_9_0->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p2_0);
$package2_0_9_0->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package2_0_9_0, 'cellog');

$package2_1_2_2 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p2_2);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package2_1_2_2, $xmlcontainer);
$package2_1_2_2->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p2_2);
$package2_1_2_2->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package2_1_2_2, 'cellog');

$package2_1_2_3 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p2_3);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package2_1_2_3, $xmlcontainer);
$package2_1_2_3->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p2_3);
$package2_1_2_3->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package2_1_2_3, 'cellog');

$package3 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p3);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package3, $xmlcontainer);
$package3->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p3);
$package3->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package3, 'cellog');

$package4 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p4);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package4, $xmlcontainer);
$package4->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p4);
$package4->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package4, 'cellog');

$package5 = new \pear2\Pyrus\Package(false);
$xmlcontainer = new \pear2\Pyrus\PackageFile($p5);
$xml = new \pear2\Pyrus\Package\Xml(__DIR__ . '/package.xml', $package5, $xmlcontainer);
$package5->setInternalPackage($xml);
file_put_contents(__DIR__ . '/package.xml', $p5);
$package5->archivefile = __DIR__ . '/package.xml';
$scs->saveRelease($package5, 'cellog');

// clean up
unlink(dirname(__DIR__) . '/pearconfig.xml');
unlink(dirname(__DIR__) . '/.config');
for ($i = 1; $i <= 5; $i++) {
    unlink(__DIR__ . "/glooby$i");
}
unlink(__DIR__ . '/package.xml');
$dir = dirname(__DIR__) . '/.configsnapshots';
include __DIR__ . '/../../../clean.php.inc';
$dir = dirname(__DIR__) . '/.xmlregistry';
include __DIR__ . '/../../../clean.php.inc';
unlink(dirname(__DIR__) . '/.pear2registry');
$dir = dirname(__DIR__) . '/PEAR2/.xmlregistry';
include __DIR__ . '/../../../clean.php.inc';
unlink(dirname(__DIR__) . '/PEAR2/.pear2registry');
rmdir(dirname(__DIR__) . '/PEAR2/temp');
rmdir(dirname(__DIR__) . '/temp');
