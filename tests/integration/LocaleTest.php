<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\sql\SqlCommands;
use Drush\Commands\core\LanguageCommands;
use Drush\Commands\core\LocaleCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

class LocaleTest extends UnishIntegrationTestCase
{
    /**
     * File name of Gettext PO source file.
     */
    protected ?string $sourceFile;

    public function setup(): void
    {
        $this->drush(PmCommands::INSTALL, ['language', 'locale']);
        $this->drush(LanguageCommands::ADD, ['nl'], ['skip-translations' => null]);

        $this->sourceFile = Path::join(__DIR__, '/resources/drush_empty_module.nl.po');

        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile]);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);
    }

    public function tearDown(): void
    {
        // Disable the locale module to make sure the database tables of locale module are emptied between tests.
        $this->drush(PmCommands::UNINSTALL, ['language', 'locale']);
    }

    public function testLocaleExport()
    {
        // Export standard translations.
        $this->drush(LocaleCommands::EXPORT, ['nl'], ['types' => 'not-customized']);
        $this->assertGettextTranslation('Drush Empty Module', 'NL Drush Empty Module');

        // Export customized translations.
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'CUSTOM Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'CUSTOM Drush Empty Module', 'nl', 1);
        $this->drush(LocaleCommands::EXPORT, ['nl'], ['types' => 'customized']);

        // Export untranslated strings.
        $this->drush(SqlCommands::QUERY, ["INSERT INTO locales_source (source) VALUES ('Something untranslated')"]);
        $this->drush(LocaleCommands::EXPORT, ['nl'], ['types' => 'not-translated']);

        // Export all.
        $this->drush(LocaleCommands::EXPORT, ['nl']);
        $this->assertGettextTranslation('Drush Empty Module', 'CUSTOM Drush Empty Module');
        $this->assertGettextTranslation('Something untranslated', '');

        // Export template file.
        $this->drush(LocaleCommands::EXPORT, ['nl'], ['template' => null]);
        $this->assertGettextTranslation('Drush Empty Module', '');
        $this->assertGettextTranslation('Something untranslated', '');
    }

    public function testLocaleImport()
    {
        // Import without override.
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'NO Drush Empty Module'"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);
        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile], ['override' => 'none']);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);

        // Import with override.
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'NO Drush Empty Module'"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);
        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile], ['override' => 'not-customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);

        // Import without override of custom translation
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile], ['override' => 'not-customized']);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);

        // Import with override of custom translation.
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile], ['override' => 'customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);

        // Import with override of custom translation as customized.
        $this->drush(SqlCommands::QUERY, ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush(LocaleCommands::IMPORT, ['nl', $this->sourceFile], ['type' => 'customized', 'override' => 'customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 1);
    }

    private function assertTranslation(string $source, string $translation, string $langcode, int $custom = 0, string $context = ''): void
    {
        $this->drush(SqlCommands::QUERY, ["SELECT ls.source, ls.context, lt.translation, lt.language, lt.customized FROM locales_source ls JOIN locales_target lt ON ls.lid = lt.lid WHERE ls.source = '$source' AND ls.context = '$context' AND lt.language = '$langcode'"]);
        $output = $this->getOutputAsList();
        $expected = "/$source.*$context.*$translation.*$langcode.*$custom/";
        $this->assertMatchesRegularExpression($expected, array_pop($output));
    }

    private function assertGettextTranslation(string $source, string $translation): void
    {
        if (strlen($source) > 71 || strlen($translation) > 71) {
            throw new \Exception('This assertion can handle strings up to 71 characters.');
        }
        $output = $this->getOutputAsList();

        $expectedSource = "msgid \"$source\"";
        $expectedTranslation = "msgstr \"$translation\"";
        $sourceLine = 0;

        // The gettext format has source (msgid) and translation (msgstr)
        // strings on consecutive lines.
        foreach ($output as $key => $row) {
            if ($row === $expectedSource) {
                $sourceLine = $key;
                break;
            }
        }
        if ($sourceLine) {
            $this->assertEquals($expectedTranslation, $output[$sourceLine + 1]);
        } else {
            $this->fail(sprintf('Source string "%s" not found', $source));
        }
    }
}
