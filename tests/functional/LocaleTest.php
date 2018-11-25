<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group pm
 */
class LocaleTest extends CommandUnishTestCase
{

    public function testLocaleExport()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm:enable', ['language', 'locale']);
        $this->drush('language:add', ['nl'], ['skip-translations' => null]);

        $source = Path::join(__DIR__, '/resources/devel.nl.po');

        $this->drush('locale:import', ['nl', $source]);
        $this->assertTranslation('Devel', 'NL Devel', 'nl', 0);

        // Export standard translations.
        $this->drush('locale:export', ['nl'], ['types' => 'not-customized']);
        $this->assertGettextTranslation('Devel', 'NL Devel');

        // Export customized translations.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'CUSTOM Devel', customized = 1"]);
        $this->assertTranslation('Devel', 'CUSTOM Devel', 'nl', 1);
        $this->drush('locale:export', ['nl'], ['types' => 'customized']);

        // Export untranslated strings.
        $this->drush('sql:query', ["INSERT locales_source SET source = 'Something untranslated'"]);
        $this->drush('locale:export', ['nl'], ['types' => 'not-translated']);

        // Export all.
        $this->drush('locale:export', ['nl']);
        $this->assertGettextTranslation('Devel', 'CUSTOM Devel');
        $this->assertGettextTranslation('Something untranslated', '');

        // Export template file.
        $this->drush('locale:export', ['nl'], ['template' => null]);
        $this->assertGettextTranslation('Devel', '');
        $this->assertGettextTranslation('Something untranslated', '');
    }

    public function testLocaleImport()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm:enable', ['language', 'locale']);
        $this->drush('language:add', ['nl'], ['skip-translations' => null]);

        $source = Path::join(__DIR__, '/resources/devel.nl.po');

        $this->drush('locale:import', ['nl', $source]);
        $this->assertTranslation('Devel', 'NL Devel', 'nl', 0);

        // Import without override.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Devel'"]);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 0);
        $this->drush('locale:import', ['nl', $source], ['override' => 'none']);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 0);

        // Import with override.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Devel'"]);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 0);
        $this->drush('locale:import', ['nl', $source], ['override' => 'not-customized']);
        $this->assertTranslation('Devel', 'NL Devel', 'nl', 0);

        // Import without override of custom translation
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Devel', customized = 1"]);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['override' => 'not-customized']);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 1);

        // Import with override of custom translation.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Devel', customized = 1"]);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['override' => 'customized']);
        $this->assertTranslation('Devel', 'NL Devel', 'nl', 0);

        // Import with override of custom translation as customized.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Devel', customized = 1"]);
        $this->assertTranslation('Devel', 'NO Devel', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['type' => 'customized', 'override' => 'customized']);
        $this->assertTranslation('Devel', 'NL Devel', 'nl', 1);
    }

    /**
     * @param string $source
     * @param string $translation
     * @param string $langcode
     * @param int $custom
     * @param string $context
     */
    private function assertTranslation($source, $translation, $langcode, $custom = 0, $context = '')
    {
        $this->drush('sql:query', ["SELECT ls.source, ls.context, lt.translation, lt.language, lt.customized FROM locales_source ls JOIN locales_target lt ON ls.lid = lt.lid WHERE ls.source = '$source' AND ls.context = '$context' AND lt.language = '$langcode'"]);
        $output = $this->getOutputAsList();
        $expected = "/$source.*$context.*$translation.*$langcode.*$custom/";
        $this->assertRegExp($expected, array_pop($output));
    }

    /**
     * @param $source
     * @param $translation
     * @param $langcode
     *
     * @throws \Exception
     */
    private function assertGettextTranslation($source, $translation)
    {
        if (strlen($source) > 71 || strlen($translation) > 71)
        {
            throw new \Exception('This assertion can handle strings up to 71 characters.');
        }
        $output = $this->getOutputAsList();

        $expectedSource = "msgid \"$source\"";
        $expectedTranslation = "msgstr \"$translation\"";
        $sourceLine = 0;

        // The gettext format has source (msgid) and translation (msgstr)
        // strings on consecutive lines.
        foreach ($output as $key => $row)
        {
            if ($row === $expectedSource) {
                $sourceLine = $key;
                break;
            }
        }
        if ($sourceLine)
        {
            $this->assertEquals($expectedTranslation, $output[$sourceLine + 1]);
        }
        else
        {
            $this->fail(sprintf('Source string "%s" not found', $source));
        }
    }
}
