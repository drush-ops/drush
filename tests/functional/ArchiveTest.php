<?php

namespace Unish;

/**
 * @todo Implement tests. A Composer-managed and "web" docroot-based SUT is required.
 *
 * @group slow
 * @group commands
 * @group archive
 */
class ArchiveTest extends CommandUnishTestCase
{
    public function testArchiveDumpCommand()
    {
        $this->drush(
            'archive:dump',
            [],
            [],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Not a Composer-managed site with "web" docroot.',
            $this->getErrorOutput()
        );
    }
}
