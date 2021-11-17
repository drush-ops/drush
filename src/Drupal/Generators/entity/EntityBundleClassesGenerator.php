<?php
namespace Drush\Drupal\Generators\entity;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Question\ChoiceQuestion;

class EntityBundleClassesGenerator extends ModuleGenerator
{
    protected string $name = 'entity:bundle-classes';
    protected string $description = 'Generate a bundle class for each content entity.';
    protected string $alias = 'ebc';
    protected string $templatePath = __DIR__;

    protected $bundleInfo;
    protected $entityTypeManager;

    public function __construct(EntityTypeBundleInfoInterface $bundleInfo, EntityTypeManagerInterface $entityTypeManager)
    {
        parent::__construct($this->name);
        $this->bundleInfo = $bundleInfo;
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars): void
    {
        $this->collectDefault($vars);
        $vars['bundle_info'] = $this->bundleInfo->getAllBundleInfo();
        $definitions = $this->entityTypeManager->getDefinitions();
        $vars['entity_types'] = array_filter($definitions, [$this, 'isContentEntity']);
        $choices = array_keys($vars['entity_types']);
        $question = new ChoiceQuestion('Entity type(s). Use comma to delimit.', $choices, 'node');
        $question->setValidator([static::class, 'validateRequired']);
        $question->setMultiselect(true);
        $vars['entity_type_ids'] = $this->io->askQuestion($question);
        $this->addFile($vars['machine_name'] . '.module', 'hook_bundle_info.php')
            // @todo When we require 2.1, use https://getcomposer.org/doc/07-runtime.md#installed-versions
            // to get path to DCG so we use its templates/_lib/file-docs/module.twig instead of a copy of that file.
            ->headerTemplate('module.twig')
            ->appendIfExists()
            ->headerSize(7);
        $vars['use_base_class'] = $this->confirm('Generate a base class? Respond no if you can easily modify the entity class.');
        foreach ($vars['entity_type_ids'] as $id) {
            $vars['entity_class'] = $vars['parent_class'] = $this->entityTypeManager->getStorage($id)->getEntityClass();
            $vars['entity_type_id'] = $id;
            if ($vars['use_base_class']) {
                $base_class = $vars['base_class'] = $vars['parent_class'] = Utils::camelize($id . 'BundleBase');
                $this->addFile("src/Entity/Bundle/$id/${base_class}.php", 'base_bundle_class.php.twig')->vars($vars);
            }
            foreach ($vars['bundle_info'][$id] as $bundle => $info) {
                $bundle_class = $vars['bundle_class'] = Utils::camelize($bundle);
                $this->addFile("src/Entity/Bundle/$id/${bundle_class}.php", 'bundle_class.php.twig')->vars($vars);
            }
        }
        $this->logger->warning('Run `drush cache:rebuild` so the bundle classes are recognized.');
    }

    protected function isContentEntity($definition)
    {
        return $definition->getGroup() == 'content';
    }
}
