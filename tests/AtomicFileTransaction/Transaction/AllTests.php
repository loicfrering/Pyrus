<?php
require_once __DIR__ . '/../../phpunit.php.inc';

class AllTests
{
    public static function suite()
    {
        $srcDir = dirname(dirname(dirname(__DIR__))) . '/src/Pyrus/AtomicFileTransaction/Transaction';

        // Setup coverage filters
        $filter = PHP_CodeCoverage::getInstance()->filter();
        $filter->addDirectoryToWhitelist($srcDir);

        // Build and return test suite
        return new PHPUnit_Extensions_PhptTestSuite(__DIR__);
    }
}