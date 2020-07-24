# generate

Generate boilerplate code for modules/plugins/services etc.

Drush asks questions so that the generated code is as polished as possible. After
generating, Drush lists the files that were created.

#### Examples

- <code>drush generate</code>. Pick from available generators and then run it.
- <code>drush generate controller</code>. Generate a controller class for your module.
- <code>drush generate drush-command-file</code>. Generate a Drush commandfile for your module.

#### Arguments

- **generator**. A generator name. Omit to pick from available Generators.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --answers=ANSWERS**. A JSON string containing pairs of question and answers.
- ** --directory=DIRECTORY**. Absolute path to a base directory for file writing.

#### Topics

- `drush docs:generators`

#### Aliases

- gen

