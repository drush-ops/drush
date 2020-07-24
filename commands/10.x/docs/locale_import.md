# locale:import

Imports to a gettext translation file.

#### Examples

- <code>drush locale-import nl drupal-8.4.2.nl.po</code>. Import the Dutch drupal core translation.
- <code>drush locale-import nl custom-translations.po --type=customized --override=all</code>. Import customized Dutch translations and override any existing translation.

#### Arguments

- **langcode**. The language code of the imported translations.
- **file**. Path and file name of the gettext file.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --type[=TYPE]**. The type of translations to be imported, defaults to 'not-customized'. Options: - customized: Treat imported strings as custom translations. - not-customized: Treat imported strings as not-custom translations.
- ** --override[=OVERRIDE]**. Whether and how imported strings will override existing translations. Defaults to the Import behavior configurred in the admin interface. Options: - none: Don't overwrite existing translations. Only append new translations. - customized: Only override existing customized translations. - not-customized: Only override non-customized translations, customized translations are kept. - all: Override any existing translation.

#### Aliases

- locale-import

