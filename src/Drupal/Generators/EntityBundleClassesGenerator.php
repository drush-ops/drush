<?php
namespace Drush\Drupal\Generators;

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
        $vars['infos'] = $this->bundleInfo->getAllBundleInfo();
        $choices = array_keys($vars['infos']);
        $question = new ChoiceQuestion('Entity type(s). Use comma to delimit.', $choices, 'node');
        $question->setValidator([static::class, 'validateRequired']);
        $question->setMultiselect(true);
        $vars['entity_type_ids'] = $this->io->askQuestion($question);
        foreach ($vars['entity_type_ids'] as $id) {
            $base_class = $vars['base_class'] = Utils::camelize($id . 'BundleBase');
            $vars['entity_class'] = '\\' . $this->entityTypeManager->getStorage($id)->getEntityClass();
            $vars['entity_type_id'] = $id;
            $this->addFile($vars['machine_name'] . '.module', 'hook_bundle_info.php')
                // @todo Get path to DCG so we use its templates/_lib/file-docs/module.twig instead of a copy of that file.
                ->headerTemplate('module.twig')
                ->appendIfExists()
                ->headerSize(7);
            $this->addFile("src/Bundle/$id/${base_class}.php", 'base_bundle_class.php.twig')->vars($vars);
            foreach ($vars['infos'][$id] as $bundle => $info) {
                $bundle_class = $vars['bundle_class'] = Utils::camelize($bundle);
                $this->addFile("src/Bundle/$id/${bundle_class}.php", 'bundle_class.php.twig')->vars($vars);
            }
        }
    }
}
