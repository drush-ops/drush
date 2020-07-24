# locale:export

Exports to a gettext translation file.

See Drupal Core: \Drupal\locale\Form\ExportForm::submitForm

#### Examples

- <code>drush locale:export nl > nl.po</code>. Export the Dutch translations with all types.
- <code>drush locale:export nl --types=customized,not-customized > nl.po</code>. Export the Dutch customized and not customized translations.
- <code>drush locale:export --template > drupal.pot</code>. Export the source strings only as template file for translation.

#### Arguments

- **langcode**. The language code of the exported translations.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --template**. POT file output of extracted source texts to be translated.
- ** --types[=TYPES]**. String types to include, defaults to all types. Types: 'not-customized', 'customized', 'not-translated'.

#### Aliases

- locale-export

