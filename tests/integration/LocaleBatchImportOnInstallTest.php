<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drush\Commands\config\ConfigCommands;
use Drush\Commands\config\ConfigExportCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\core\LanguageCommands;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\core\StatusCommands;
use Drush\Commands\core\WatchdogCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group locale
 * @group pm
 * @group config
 */
class LocaleBatchImportOnInstallTest extends CommandUnishTestCase
{
    protected string $translationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $info_yml = Path::join($this->webroot(), 'modules/unish/drush_empty_module/drush_empty_module.info.yml');
        if (!str_contains(file_get_contents($info_yml), 'project:') || $this->isWindows()) {
            $this->markTestSkipped('Devel dev snapshot detected. Incompatible with translation import.');
        }

        $this->setUpDrupal(1, true);
        $root = $this->webroot();

        $this->drush(PmCommands::INSTALL, ['language', 'locale', 'dblog']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.import_enabled', true]);

        // Setup the interface translation system and prepare a source translation file.
        // The test uses a local po file as translation source. This po file will be
        // imported from the translations directory when a module is enabled.
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.use_source', 'locale']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.default_filename', '%project.%language.po']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.path', '../translations']);
        $source = Path::join(__DIR__, 'resources/drush_empty_module.nl.po');
        $this->translationDir = Path::join($root, '../translations');
        $this->mkdir($this->translationDir);
        copy($source, Path::join($this->translationDir, 'drush_empty_module.nl.po'));

        $this->drush(LanguageCommands::ADD, ['nl']);
    }

    public function testBatchImportTranslationsOnInstall()
    {
        $this->drush(PmCommands::INSTALL, ['drush_empty_module']);
        $this->drush(WatchdogCommands::SHOW);
        $this->assertStringContainsString('Translations imported:', $this->getSimplifiedOutput());
    }

    public function testBatchImportTranslationsOnConfigImport()
    {
        $this->drush(ConfigExportCommands::EXPORT);

        $this->drush(StatusCommands::STATUS, [], ['format' => 'json', 'fields' => 'config-sync']);
        $core_sync_dir = $this->webroot() . '/' . $this->getOutputFromJSON('config-sync');

        $core_extension_file = "$core_sync_dir/core.extension.yml";
        $core_extension_file_backup = "$core_sync_dir/core.extension.yml.backup";

        // Add drush_empty_module in core.extension.
        copy($core_extension_file, $core_extension_file_backup);
        $extensions = Yaml::decode(file_get_contents($core_extension_file));
        $extensions['module']['drush_empty_module'] = 0;
        file_put_contents($core_extension_file, Yaml::encode($extensions));

        $this->drush(ConfigImportCommands::IMPORT, [], ['yes' => null]);
        $this->assertStringContainsString('[notice] Checked nl translation for drush_empty_module.', $this->getErrorOutput());
        $this->drush(WatchdogCommands::SHOW);
        $this->assertStringContainsString('Translations imported: 1 added, 0 updated, 0 removed.', $this->getOutput());

        // Restore original file.
        unlink($core_extension_file);
        rename($core_extension_file_backup, $core_extension_file);
    }

    public function tearDown(): void
    {
        $this->drush(PhpCommands::EVAL, ['Drupal\language\Entity\ConfigurableLanguage::load("nl")->delete()']);
        $this->drush(PmCommands::UNINSTALL, [
          'language',
          'locale',
          'dblog',
          'drush_empty_module',
        ]);
        rmdir($this->translationDir);

        parent::tearDown();
    }
}
