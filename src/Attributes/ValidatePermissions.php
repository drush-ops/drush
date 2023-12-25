<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drush\Utils\StringUtils;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidatePermissions extends ValidatorBase implements ValidatorInterface
{
    /**
     * @param $argName
     *   The argument name containing the required permissions.
     */
    public function __construct(
        public string $argName,
    ) {
    }

    public function validate(CommandData $commandData)
    {
        $missing = [];
        $arg_or_option_name = $this->argName;
        if ($commandData->input()->hasArgument($arg_or_option_name)) {
            $permissions = StringUtils::csvToArray($commandData->input()->getArgument($arg_or_option_name));
        } else {
            $permissions = StringUtils::csvToArray($commandData->input()->getOption($arg_or_option_name));
        }
        $all_permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());
        $missing = array_diff($permissions, $all_permissions);
        if ($missing) {
            $msg = dt('Permission(s) not found: !perms', ['!perms' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
