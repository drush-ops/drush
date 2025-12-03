<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property InputInterface $input
 * @property EntityTypeBundleInfoInterface $entityTypeBundleInfo
 * @property EntityTypeManagerInterface $entityTypeManager
 */
trait EntityTypeBundleAskTrait
{
    protected function getFieldableEntityTypes(): array
    {
        return array_filter(
            $this->entityTypeManager->getDefinitions(),
            fn (EntityTypeInterface $entityType) => $entityType->entityClassImplements(FieldableEntityInterface::class)
        );
    }

    protected function askEntityType(InputInterface $input, OutputInterface $output): ?string
    {
        $entityTypeDefinitions = $this->getFieldableEntityTypes();
        $choices = [];

        foreach ($entityTypeDefinitions as $entityTypeDefinition) {
            $choices[$entityTypeDefinition->id()] = $input->getOption('show-machine-names')
                ? $entityTypeDefinition->id()
                : $entityTypeDefinition->getLabel();
        }

        $io = new DrushStyle($input, $output);
        if (!$answer = $io->select('Entity type', $choices, required: true)) {
            throw new \InvalidArgumentException(dt('The entityType argument is required.'));
        }

        return $answer;
    }

    protected function askBundle(InputInterface $input, OutputInterface $output): ?string
    {
        $entityTypeId = $input->getArgument('entityType');
        $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId);
        $bundleEntityType = $entityTypeDefinition->getBundleEntityType();
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
        $choices = [];

        if ($bundleEntityType && $bundleInfo === []) {
            throw new \InvalidArgumentException(
                dt("Entity type with id '!entityType' does not have any bundles.", ['!entityType' => $entityTypeId])
            );
        }

        if (!$bundleEntityType && count($bundleInfo) === 1) {
            // eg. User
            return $entityTypeId;
        }

        foreach ($bundleInfo as $bundle => $data) {
            $label = $input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        $io = new DrushStyle($input, $output);
        if (!$answer = $io->select('Bundle', $choices)) {
            throw new \InvalidArgumentException(dt('The bundle argument is required.'));
        }

        return $answer;
    }
}
