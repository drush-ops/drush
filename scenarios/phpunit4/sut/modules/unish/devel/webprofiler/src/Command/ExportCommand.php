<?php

namespace Drupal\webprofiler\Command;

use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\webprofiler\Profiler\Profiler;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * Class ExportCommand
 *
 * @DrupalCommand (
 *     extension="webprofiler",
 *     extensionType="module"
 * )
 */
class ExportCommand extends Command {

  use ContainerAwareCommandTrait;

  /** @var string */
  private $filename;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('webprofiler:export')
      ->setDescription($this->trans('commands.webprofiler.export.description'))
      ->addArgument('id', InputArgument::OPTIONAL, $this->trans('commands.webprofiler.export.arguments.id'))
      ->addOption('directory', NULL, InputOption::VALUE_REQUIRED, $this->trans('commands.webprofiler.export.options.directory'), '/tmp');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $id = $input->getArgument('id');
    $directory = $input->getOption('directory');

    /** @var \Drupal\webprofiler\Profiler\Profiler $profiler */
    $profiler = $this->container->get('profiler');

    try {
      if ($id) {
        $this->filename = $this->exportSingle($profiler, $id, $directory);
      }
      else {
        $this->filename = $this->exportAll($profiler, $directory, $output);
      }

    } catch (\Exception $e) {
      $output->writeln('<error>' . $e->getMessage() . '</error>');
    }
  }

  /**
   * Exports a single profile.
   *
   * @param \Drupal\webprofiler\Profiler\Profiler $profiler
   * @param int $id
   * @param string $directory
   *
   * @return string
   *
   * @throws \Exception
   */
  private function exportSingle(Profiler $profiler, $id, $directory) {
    $profile = $profiler->loadProfile($id);
    if ($profile) {
      $data = $profiler->export($profile);

      $filename = $directory . DIRECTORY_SEPARATOR . $id . '.txt';
      if (file_put_contents($filename, $data) === FALSE) {
        throw new \Exception(sprintf(
          $this->trans('commands.webprofiler.export.messages.error_writing'),
          $filename));
      }
    }
    else {
      throw new \Exception(sprintf(
        $this->trans('commands.webprofiler.export.messages.error_no_profile'),
        $id));
    }

    return $filename;
  }

  /**
   * Exports all stored profiles (cap limit at 1000 items).
   *
   * @param \Drupal\webprofiler\Profiler\Profiler $profiler
   * @param string $directory
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  private function exportAll(Profiler $profiler, $directory, $output) {
    $filename = $directory . DIRECTORY_SEPARATOR . 'profiles_' . time() . '.tar.gz';
    $archiver = new ArchiveTar($filename, 'gz');
    $profiles = $profiler->find(NULL, NULL, 1000, NULL, '', '');
    $progress = new ProgressBar($output, count($profiles) + 2);
    $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

    $files = [];
    $progress->start();
    $progress->setMessage($this->trans('commands.webprofiler.export.progress.exporting'));
    foreach ($profiles as $profile) {
      $data = $profiler->export($profiler->loadProfile($profile['token']));
      $profileFilename = $directory . "/{$profile['token']}.txt";
      file_put_contents($profileFilename, $data);
      $files[] = $profileFilename;
      $progress->advance();
    }

    $progress->setMessage($this->trans('commands.webprofiler.export.progress.archive'));
    $archiver->createModify($files, '', $directory);
    $progress->advance();

    $progress->setMessage($this->trans('commands.webprofiler.export.progress.delete_tmp'));
    foreach ($files as $file) {
      unlink($file);
    }
    $progress->advance();

    $progress->setMessage($this->trans('commands.webprofiler.export.progress.done'));
    $progress->finish();
    $output->writeln('');

    $output->writeln(sprintf(
      $this->trans('commands.webprofiler.export.messages.exported_count'),
      count($profiles)));

    return $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function showMessage($output, $message, $type = 'info') {
    if (!$this->filename) {
      return;
    }

    $completeMessageKey = 'commands.webprofiler.export.messages.success';
    $completeMessage = sprintf($this->trans($completeMessageKey), $this->filename);

    if ($completeMessage != $completeMessageKey) {
      parent::showMessage($output, $completeMessage);
    }
  }
}
