This module creates the "DevelGenerate" plugin type.

All you need to do to provide a new instance for "DevelGenerate" plugin type
is to create your class extending "DevelGenerateBase" and following the next steps.

1 - Declaring your plugin with annotations:

/**
 * Provides a ExampleDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "example",
 *   label = @Translation("example"),
 *   description = @Translation("Generate a given number of example elements. Optionally delete current example elements."),
 *   url = "example",
 *   permission = "administer example",
 *   settings = {
 *     "num" = 50,
 *     "kill" = FALSE,
 *     "another_property" = "default_value"
 *   }
 * )
 */

2 - Implement "settingsForm" method to create a form using the properties from annotations.

3 - Implement "handleDrushParams" method. It should return an array of values.

4 - Implement "generateElements" method. You can write here your business logic
using the array of values.

Notes:

You can alter existing properties for every plugin implementing hook_devel_generate_info_alter.

DevelGenerateBaseInterface details base wrapping methods that most DevelGenerate implementations
will want to directly inherit from Drupal\devel_generate\DevelGenerateBase.

To give support for a new field type the field type base class should properly
implements \Drupal\Core\Field\FieldItemInterface::generateSampleValue().
Devel generate automatically use the values returned by this method during the
generate process for generate placeholder field values. For more information
see:
https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FieldItemInterface.php/function/FieldItemInterface::generateSampleValue
