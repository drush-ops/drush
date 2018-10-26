<?php

namespace Drupal\webprofiler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * Class ListCommand
 **
 * @DrupalCommand (
 *     extension="webprofiler",
 *     extensionType="module"
 * )
 */
class ListCommand extends Command {

  use ContainerAwareCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('webprofiler:list')
      ->setDescription($this->trans('commands.webprofiler.list.description'))
      ->addOption('ip', NULL, InputOption::VALUE_REQUIRED, $this->trans('commands.webprofiler.list.options.ip'), NULL)
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, $this->trans('commands.webprofiler.list.options.url'), NULL)
      ->addOption('method', NULL, InputOption::VALUE_REQUIRED, $this->trans('commands.webprofiler.list.options.method'), NULL)
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, $this->trans('commands.webprofiler.list.options.limit'), 10);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $ip = $input->getOption('ip');
    $url = $input->getOption('url');
    $method = $input->getOption('method');
    $limit = $input->getOption('limit');

    /** @var \Drupal\webprofiler\Profiler\Profiler $profiler */
    $profiler = $this->container->get('profiler');
    $profiles = $profiler->find($ip, $url, $limit, $method, '', '');

    $rows = [];
    foreach ($profiles as $profile) {
      $row = [];

      $row[] = $profile['token'];
      $row[] = $profile['ip'];
      $row[] = $profile['method'];
      $row[] = $profile['url'];
      $row[] = date($this->trans('commands.webprofiler.list.rows.time'), $profile['time']);

      $rows[] = $row;
    }

    $table = new Table($output);
    $table
      ->setHeaders([
        $this->trans('commands.webprofiler.list.header.token'),
        $this->trans('commands.webprofiler.list.header.ip'),
        $this->trans('commands.webprofiler.list.header.method'),
        $this->trans('commands.webprofiler.list.header.url'),
        $this->trans('commands.webprofiler.list.header.time'),
      ])
      ->setRows($rows);
    $table->render();
  }

  /**
   * {@inheritdoc}
   */
  public function showMessage($output, $message, $type = 'info') {
  }
}
