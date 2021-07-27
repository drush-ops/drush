<?php
namespace Drush\Commands;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DrushAttributes
{
    /**
     * DrushAttributes constructor.
     *
     * @param $command
     *   The command's name.
     * @param $hook
     *   The command's name.
     * @param $custom
     *   Custom name/value pairs that may be used by command(s).
     * @param $name
     *   The name of the command. Usually use 'command' or 'hook' instead of 'name'.
     * @param $description
     *   One sentence describing the command or hook
     * @param $help
     *   A multi-sentence help text about the item.
     * @param $hidden
     *   Hide a command from help list.
     * @param $hidden_options
     *   Hide an option from help detail.
     * @param $aliases
     *   A simple array of topic names.
     * @param $usages
     *   An array of use examples and descriptions.
     * @param $options
     *   An array of name -> description pairs.
     * @param $optionset_proc_build
     *  Process building options.
     * @param $optionset_get_editor
     *  Interactive editor options.
     * @param $optionset_ssh
     *  Standard SSH options.
     * @param $optionset_sql
     *  Standard SQL options.
     * @param $optionset_table_selection
     *  Standard table selection options.
     * @param $params
     *   An array of name -> description pairs.
     * @param $topic
     *   Indicate that a command is a help topic.
     * @param $topics
     *   A simple list of applicable help topics.
     * @param $validate_entity_load
     *   An entity type and ID separated by a space which must load successfully.
     * @param $validate_file_exists,
     *   Value is the name of the argument/option containing the path.
     * @param $validate_module_enabled
     *   Value is a module name.
     * @param $validate_permissions
     *   Value is the name of the argument/option containing the permission.
     * @param string|null $validate_php_extension
     *   Value should be extension name. If multiple, delimit by a comma.
     */
    public function __construct(
        public ?string $command,
        public ?string $description,
        public ?array $options,
        public ?array $params,
        public ?array $usages,
        public ?array $aliases,
        public ?array $topics,
        public ?array $custom,
        public ?string $help,
        public ?bool $hidden,
        public ?string $hidden_options,
        public ?string $hook,
        public ?string $name,
        public ?string $topic,
        public ?bool $optionset_proc_build,
        public ?bool $optionset_get_editor,
        public ?bool $optionset_ssh,
        public ?bool $optionset_sql,
        public ?bool $optionset_table_selection,
        public ?string $validate_entity_load,
        public ?string $validate_module_enabled,
        public ?string $validate_file_exists,
        public ?string $validate_php_extension,
        public ?string $validate_permissions,
    ) {
    }
}
