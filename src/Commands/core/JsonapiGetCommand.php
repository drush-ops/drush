<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Drupal\Core\DrupalKernelInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Execute a JSONAPI request.',
    aliases: ['jn:get'],
)]
#[CLI\ValidateModulesEnabled(modules: ['jsonapi'])]
#[CLI\Formatter(returnType: UnstructuredData::class, defaultFormatter: 'json')]
final class JsonapiGetCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'jn:get';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        #[Autowire(service: 'kernel')]
        protected readonly DrupalKernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'The JSONAPI URL to request.')
            ->addUsage('jn:get jsonapi/node/article')
            ->addUsage('jn:get jsonapi/node/article | jq')
            ->setHelp('Get a list of articles back as JSON. Pretty print JSON by piping to jq. See https://stedolan.github.io/jq/ for lots more jq features.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $data = $this->doExecute($url);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(string $url): UnstructuredData
    {
        $sub_request = Request::create($url, 'GET');
        $subResponse = $this->kernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
        return new UnstructuredData(json_decode($subResponse->getContent()));
    }
}
