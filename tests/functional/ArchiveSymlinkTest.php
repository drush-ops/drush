<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\ArchiveDumpCommands;
use Drush\Commands\core\ArchiveRestoreCommands;
use Drush\Commands\core\StatusCommands;
use PharData;
use Symfony\Component\Filesystem\Path;
use Unish\Utils\FSUtils;

/**
 * @group slow
 * @group commands
 * @group archive
 */
class ArchiveSymlinkTest extends CommandUnishTestCase
{
    use FSUtils;

    protected string $archivePath;
    protected array $archiveDumpOptions;
    protected string $linktarget;
    protected string $linkdestination;

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $this->setUpDrupal(1, true);
        $this->archiveDumpOptions = [
            'db' => null,
            'files' => null,
            'code' => null,
            'exclude-code-paths' => 'sut/sites/.+/settings.php,(?!sut|composer\.json|composer\.lock).*',
        ];

        $this->archivePath = Path::join($this->getSandbox(), 'archive.tar.gz');

        $this->linktarget      = Path::join($this->getSandbox(), 'symlinktest.txt');
        $this->linkdestination = Path::join($this->webroot(), 'symlinkdest.txt');

        file_put_contents($this->linktarget, "This is a symlink target file.");
        symlink($this->linktarget, $this->linkdestination);
    }

    public function tearDown(): void
    {
        unlink($this->linktarget);
        unlink($this->linkdestination);
    }

    public function testArchiveDumpSymlinkReplaceCommand(): void
    {
        // The symlinks written in setup would cause the PharData class to
        // fail if we did not replace them before archiving.
        // @see https://github.com/drush-ops/drush/pull/6030
        $this->drush(
            ArchiveDumpCommands::DUMP,
            [],
            array_merge($this->archiveDumpOptions, [
                'destination' => $this->archivePath,
                'overwrite' => null,
            ])
        );
    }
}
