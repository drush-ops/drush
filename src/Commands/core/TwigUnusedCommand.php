<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: self::UNUSED,
    description: 'Find potentially unused Twig templates.',
    aliases: ['twu']
)]
#[CLI\FieldLabels(labels: ['template' => 'Template', 'compiled' => 'Compiled'])]
#[CLI\DefaultTableFields(fields: ['template', 'compiled'])]
#[CLI\FilterDefaultField(field: 'template')]
final class TwigUnusedCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    const UNUSED = 'twig:unused';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected readonly BootstrapManager $bootstrapManager,
        protected readonly TwigEnvironment $twig,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('searchpaths', InputArgument::REQUIRED, 'A comma delimited list of paths to recursively search')
            // Usages can't have a description with plain Console :(. Use setHelp() if desired as per  https://github.com/symfony/symfony/issues/45050
            ->addUsage('twig:unused /var/www/mass.local/docroot/modules/custom,/var/www/mass.local/docroot/themes/custom')
            ->setHelp('Immediately before running this command, web crawl your entire web site. Or use your Production PHPStorage dir for comparison.');
        $this->addFormatterOptions();
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $searchpaths = $input->getArgument('searchpaths');
        $unused = [];
        $phpstorage = PhpStorageFactory::get('twig');

        // Find all templates in the codebase.
        $files = Finder::create()
            ->files()
            ->name('*.html.twig')
            ->exclude('tests')
            ->in(StringUtils::csvToArray($searchpaths));
        $this->logger->notice(dt('Found !count templates', ['!count' => count($files)]));

        // Check to see if a compiled equivalent exists in PHPStorage
        foreach ($files as $file) {
            $relative = Path::makeRelative($file->getRealPath(), $this->bootstrapManager->getRoot());
            $mainCls = $this->twig->getTemplateClass($relative);
            $cache = $this->twig->getCache();
            if ($cache) {
                $key = $cache->generateKey($relative, $mainCls);
                if (!$phpstorage->exists($key)) {
                    $unused[$key] = [
                        'template' => $relative,
                        'compiled' => $key,
                    ];
                }
            } else {
                throw new \Exception('There was a problem, please ensure your twig cache is enabled.');
            }
        }
        $this->logger->notice(dt('Found !count unused', ['!count' => count($unused)]));
        return new RowsOfFields($unused);
    }
}
