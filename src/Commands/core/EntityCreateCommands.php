<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\SiteProcess\Util\Escape;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Yaml\Yaml;

final class EntityCreateCommands extends DrushCommands
{
    use AutowireTrait;

    const CREATE = 'entity:create';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityFieldManagerInterface $entityFieldManager,
        protected AccountSwitcherInterface $accountSwitcher
    ) {
        parent::__construct();
    }

    /**
     * Create a content entity after prompting for field values.
     *
     * When entering field values, one may submit an incomplete document and any entity violations
     * will be helpfully reported at the top of the document. enter <info>skip</info> as
     * a value in order to skip validation for that field. Timestamp values may be expressed via any string
     * recognized by strtotime()
     */
    #[CLI\Command(name: self::CREATE, aliases: ['econ', 'entity-create'])]
    #[CLI\Argument(name: 'entity_type', description: 'An entity type name.')]
    #[CLI\Argument(name: 'bundle', description: 'A bundle name')]
    #[CLI\Option(name: 'uid', description: 'The entity author ID. Also used by permission checks (e.g. content moderation)')]
    #[CLI\Option(name: 'skip-fields', description: 'A list of field names that skip both data entry and validation. Delimit fields by comma')]
    #[CLI\Option(name: 'validate', description: 'Validate the entity before saving.')]
    #[CLI\OptionsetGetEditor]
    #[CLI\Usage(name: 'drush entity:create node article --validate=0', description: 'Create an article entity and skip validation entirely.')]
    #[CLI\Usage(name: 'drush entity:create node article --skip-fields=field_media_image,field_tags', description: 'Create an article omitting two fields.')]
    #[CLI\Usage(name: 'drush entity:create user user --editor=nano', description: 'Create a user using the Nano text editor.')]
    #[CLI\Version(version: '12.5')]
    public function createEntity(string $entity_type, $bundle, array $options = ['validate' => true, 'uid' => self::REQ, 'skip-fields' => self::REQ]): string
    {
        $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
        /** @var ContentEntityInterface $entity */
        $entity = $this->entityTypeManager->getStorage($entity_type)->create([$bundleKey => $bundle]);
        $instances = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
        $skip_fields = StringUtils::csvToArray($options['skip-fields']);
        if ($skip_fields) {
            $instances = array_diff_key($instances, array_flip($skip_fields));
        }
        ksort($instances);
        $yaml = $this->getInitialYaml($instances, $entity, $options);
        // Write tmp YAML file for editing
        $path = drush_save_data_to_temp_file($yaml, '.yml');
        do {
            $messages = [];
            $yaml = $this->edit($options['editor'], $path);
            try {
                $values = Yaml::parse($yaml);
            } catch (\Exception $e) {
                $yaml = "# Error: {$e->getMessage()}\n" . $yaml;
                file_put_contents($path, $yaml);
                continue;
            }
            foreach (array_filter($values) as $name => $value) {
                if ($value !== 'skip') {
                    $this->setValue($entity, $name, $value);
                } else {
                    $skip_fields[] = $name;
                }
            }

            if (!$options['validate']) {
                break;
            }
            $violations = $entity->validate();
            $this->filterViolations($violations, $skip_fields);
            if (!$violations->count()) {
                break;
            }
            $this->removePreamble($yaml, $lines);

            // Switch needed to overcome Content Moderation constraint.
            if ($options['uid']) {
                $this->accountSwitcher->switchTo($this->entityTypeManager->getStorage('user')->load($options['uid']));
            }
            foreach ($violations as $violation) {
                $messages[] = "# {$violation->getPropertyPath()}: {$violation->getMessage()}";
            }
            file_put_contents($path, "# Violations:\n" . implode("\n", $messages) . "\n" . implode("\n", $lines));
        } while (true);
        $entity->save();
        return $entity->toUrl('canonical', ['absolute' => true])->toString();
    }

    private function edit($editor, string $path): string
    {
        $exec = self::getEditor($editor);
        $cmd = sprintf($exec, Escape::shellArg($path));
        $process = $this->processManager()->shell($cmd);
        $process->setTty(true);
        $process->mustRun();
        return file_get_contents($path);
    }


    /**
     * Build initial YAML including comments with authoring hints.
     *
     * @param FieldDefinitionInterface[] $instances
     */
    private function getInitialYaml(array $instances, ContentEntityInterface $entity, array $options): string
    {
        $lines = $comments = [];
        // Build field names and default value.
        foreach ($instances as $field_name => $instance) {
            $comment = [];
            if (!$this->showField($instance)) {
                continue;
            }
            $cardinality = $instance->getFieldStorageDefinition()->getCardinality();
            $multiple = $instance->getFieldStorageDefinition()->isMultiple();
            $suffix = $multiple ? '[]' : '';
            if ($instance->isRequired()) {
                $comment[] = 'required';
            }
            if (!in_array($cardinality, [1, FieldStorageConfig::CARDINALITY_UNLIMITED])) {
                $comment[] = "max: $cardinality";
            }
            // Get a simple default value if defined.
            $default_value = '';
            if ($field_name == 'uid') {
                $default_value = $options['uid'];
            } elseif ($default = $instance->getDefaultValue($entity)) {
                if (count($default) == 1 && (count($default[0]) == 1)) {
                    // var_export converts boolean to "true".
                    $default_value = var_export(reset($default[0]), true);
                    ;
                }
            }
            $comments[] = $comment;
            $lines[] = "$field_name: $default_value" . $suffix;
        }
        // Append comment as needed, using padding.
        $max_len = max(array_map('strlen', $lines));
        foreach ($lines as $key => $line) {
            if ($comments[$key]) {
                $lines[$key] = str_pad($line, $max_len + 1) . '# ' . implode(', ', $comments[$key]);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Show/hide a field when building initial YAML.
     */
    private function showField(FieldDefinitionInterface $instance): bool
    {
        if ($instance->isReadOnly()) {
            return false;
        }
        if (in_array($instance->getType(), ['image', 'layout_section', 'changed', 'created'])) {
            return false;
        }
        foreach (['revision', 'content_translation', 'default_langcode', 'revision', 'content_translation'] as $deny) {
            if (str_starts_with($instance->getName(), $deny)) {
                return false;
            }
        }
        return true;
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR)]
    public function validate(): void
    {
        if (!$this->input()->isInteractive()) {
            throw new \RuntimeException('entity:create is designed for an interactive terminal.');
        }
    }

    private function removePreamble(string $yaml, &$lines): void
    {
        $lines = explode("\n", $yaml);
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '#')) {
                unset($lines[$index]);
            }
        }
    }

    private function filterViolations(EntityConstraintViolationListInterface &$violations, array $skip_fields): void
    {
        foreach ($violations as $key => $violation) {
            if (in_array($violation->getPropertyPath(), $skip_fields)) {
                $violations->remove($key);
            }
        }
    }

    protected function setValue(ContentEntityInterface $entity, int|string $name, mixed $value): void
    {
        switch ($entity->get($name)->getFieldDefinition()->getType()) {
            case 'timestamp':
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                // Keep going.
            default:
                $entity->set($name, $value);
                break;
        }
    }
}
