<?php
namespace Drush\Log;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drush\Drush;
use Psr\Log\LoggerInterface;

class LoggerDrupalToDrush implements LoggerInterface
{
    use RfcLoggerTrait;

    /**
     * @var \Drush\Log\DrushLog
     */
    protected $adapter;

    public function __construct(LogMessageParserInterface $parser)
    {
        $this->adapter = new DrushLog($parser, Drush::logger());
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        $this->adapter->log($level, $message, $context);
    }
}
