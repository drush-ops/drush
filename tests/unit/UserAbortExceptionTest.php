<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\TestCase;

/**
 * Tests the UserAbortException to ensure that it does not reference
 * anything that might break on older versions of PHP (e.g. \Throwable).
 *
 * @group base
 */
class UserAbortExceptionTest extends TestCase
{
    /**
     * @covers \Drush\Exceptions\UserAbortException
     */
    public function testUserAbortException()
    {
        $this->expectException('\Drush\Exceptions\UserAbortException');
        throw new \Drush\Exceptions\UserAbortException('This is an exception');
    }

    /**
     * Declare an exception handler but do not trigger it
     */
    public function testCatchWithoutThrow()
    {
        $abort = null;

        try {
            $abort = new \Drush\Exceptions\UserAbortException('This is an exception');
        } catch (\Throwable $e) {
            // We should not get here, because no one threw the exception
            $abort = new \Drush\Exceptions\UserAbortException('Abort after failure', 1, $e);
        }

        $this->assertEquals('This is an exception', $abort->getMessage());
    }

    /**
     * Catch and re-throw a throwable
     */
    public function testRethrow()
    {
        $version = phpversion();
        if ($version[0] == '5') {
            $this->markTestSkipped("Can't actually catch a Throwable in PHP 5");
        }

        try {
            throw new \Exception('This is the original exception');
        } catch (\Throwable $e) {
            $this->expectException('\Drush\Exceptions\UserAbortException');
            // There really isn't any use-case for this, but our API allows it.
            throw new \Drush\Exceptions\UserAbortException('Abort after failure', 1, $e);
        }
    }

    /**
     * Catch and re-throw an exception
     */
    public function testRethrowException()
    {
        try {
            throw new \Exception('This is the original exception');
        } catch (\Exception $e) {
            $this->expectException('\Drush\Exceptions\UserAbortException');
            // There really isn't any use-case for this, but our API allows it.
            throw new \Drush\Exceptions\UserAbortException('Abort after failure', 1, $e);
        } catch (\Throwable $e) {
            throw new \Drush\Exceptions\UserAbortException('We should never get here', 1, $e);
        }
    }
}
