<?php

declare(strict_types=1);

namespace Drush\Commands\locale;

use Drupal\Component\Gettext\PoStreamWriter;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\PoDatabaseReader;
use Drush\Exceptions\CommandFailedException;
use Psr\Log\LoggerInterface;

trait LocaleTrait
{
    protected LanguageManagerInterface $languageManager;
    protected ConfigFactoryInterface $configFactory;
    protected ModuleHandlerInterface $moduleHandler;
    protected StateInterface $state;
    protected LoggerInterface $logger;

    /**
     * Converts input of translation type.
     *
     * @param $type
     */
    private function convertCustomizedType($type): int
    {
        return $type == 'customized' ? LOCALE_CUSTOMIZED : LOCALE_NOT_CUSTOMIZED;
    }

    /**
     * Converts input of override option.
     *
     * @param $override
     */
    private function convertOverrideOption($override): array
    {
        $result = [];

        switch ($override) {
            case 'none':
                $result = [
                    'not_customized' => false,
                    'customized' => false,
                ];
                break;

            case 'customized':
                $result = [
                    'not_customized' => false,
                    'customized' => true,
                ];
                break;

            case 'not-customized':
                $result = [
                    'not_customized' => true,
                    'customized' => false,
                ];
                break;

            case 'all':
                $result = [
                    'not_customized' => true,
                    'customized' => true,
                ];
                break;
        }

        return $result;
    }

    /**
     * Get translatable language object.
     *
     * @param string $langcode The language code of the language object.
     * @param bool $addLanguage Create language when not available.
     * @return LanguageInterface|null
     * @throws \Exception
     */
    private function getTranslatableLanguage(string $langcode, bool $addLanguage = false)
    {
        if (!$langcode) {
            return null;
        }

        $language = $this->languageManager->getLanguage($langcode);

        if (!$language) {
            if ($addLanguage) {
                $language = ConfigurableLanguage::createFromLangcode($langcode);
                $language->save();

                $this->logger->notice('Added language {language}', [
                    'language' => $language->label(),
                ]);
            } else {
                throw new CommandFailedException(sprintf('Language code %s is not configured.', $langcode));
            }
        }

        if (!$this->isTranslatable($language)) {
            throw new CommandFailedException(sprintf('Language code %s is not translatable.', $langcode));
        }

        return $language;
    }

    /**
     * Check if language is translatable.
     */
    private function isTranslatable(LanguageInterface $language): bool
    {
        if ($language->isLocked()) {
            return false;
        }

        if ($language->getId() != 'en') {
            return true;
        }

        return (bool)$this->configFactory
            ->get('locale.settings')
            ->get('translate_english');
    }

    /**
     * Get PODatabaseReader options for given types.
     *
     * @param array $types
     *   Options list with value 'true'.
     * @throws \Exception
     *   Triggered with incorrect types.
     */
    private function convertTypesToPoDbReaderOptions(array $types = []): array
    {
        $valid_convertions = [
            'not_customized' => 'not-customized',
            'customized' => 'customized',
            'not_translated' => 'not-translated',
        ];

        if ($types === []) {
            return array_fill_keys(array_keys($valid_convertions), true);
        }

        // Check for invalid conversions.
        if (array_diff($types, $valid_convertions)) {
            throw new CommandFailedException(sprintf('Allowed types: %s.', implode(', ', $valid_convertions)));
        }

        // Convert Types to Options.
        $options = array_keys(array_intersect($valid_convertions, $types));

        return array_fill_keys($options, true);
    }

    /**
     * Write out the exported language or template file.
     *
     * @param string $file_uri Uri string to gather the data.
     * @param LanguageInterface|null $language The language to export.
     * @param array $options The export options for PoDatabaseReader.
     * @return bool True if successful.
     */
    private function writePoFile(string $file_uri, ?LanguageInterface $language = null, array $options = []): bool
    {
        $reader = new PoDatabaseReader();

        if ($language) {
            $reader->setLangcode($language->getId());
            $reader->setOptions($options);
        }

        $reader_item = $reader->readItem();
        if (empty($reader_item)) {
            return false;
        }

        $header = $reader->getHeader();
        $header->setProjectName($this->configFactory->get('system.site')->get('name'));
        $language_name = ($language) ? $language->getName() : '';
        $header->setLanguageName($language_name);

        $writer = new PoStreamWriter();
        $writer->setURI($file_uri);
        $writer->setHeader($header);
        $writer->open();
        $writer->writeItem($reader_item);
        $writer->writeItems($reader);
        $writer->close();

        return true;
    }
}
