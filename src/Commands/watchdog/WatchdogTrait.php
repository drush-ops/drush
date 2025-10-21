<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\User;
use Drush\Drupal\DrupalUtil;
use Psr\Log\LoggerInterface;

trait WatchdogTrait
{
    protected Connection $connection;
    protected LoggerInterface $logger;

    /**
     * Build a WHERE snippet based on given parameters.
     *
     * Example: ('where' => string, 'args' => [])
     *
     * @param $type
     *   String. Valid watchdog type.
     * @param $severity
     *   Int or String for a valid watchdog severity message.
     * @param $filter
     *   String. Value to filter watchdog messages by.
     * @param $criteria
     *   ('AND', 'OR'). Criteria for the WHERE snippet.
     * @param $severity_min
     *   Int or String for the minimum severity to return.
     */
    protected function where(?string $type = null, $severity = null, ?string $filter = null, string $criteria = 'AND', int|string|null $severity_min = null): array
    {
        $args = $levels = $conditions = [];
        if ($type) {
            $types = $this->messageTypes();
            if (!in_array($type, $types)) {
                $msg = "Unrecognized message type: %s.\nRecognized types are: %s.";
                throw new \Exception(sprintf($msg, $type, implode(', ', $types)));
            }
            $conditions[] = "type = :type";
            $args[':type'] = $type;
        }
        if (!empty($severity) && !empty($severity_min)) {
            $msg = "--severity=%s  --severity-min=%s\nYou may provide a value for one of these parameters but not both.";
            throw new \Exception(sprintf($msg, $severity, $severity_min));
        }
        // From here we know that only one of --severity or --severity-min might
        // have a value but not both of them.
        if (!empty($severity) || !empty($severity_min)) {
            if (empty($severity)) {
                $severity = $severity_min;
                $operator = '<=';
            } else {
                $operator = '=';
            }
            $severities = RfcLogLevel::getLevels();
            if (isset($severities[$severity])) {
                $level = $severity;
            } elseif (($key = array_search($severity, $severities)) !== false) {
                $level = $key;
            } else {
                $level = false;
            }
            if ($level === false) {
                foreach ($severities as $key => $value) {
                    $levels[] = "$value($key)";
                }
                $msg = "Unknown severity level: %s\nValid severity levels are: %s.\nEither use the default language levels, or use a number.";
                throw new \Exception(sprintf($msg, $severity, implode(', ', $levels)));
            }
            $conditions[] = "severity $operator :severity";
            $args[':severity'] = $level;
        }
        if ($filter) {
            $conditions[] = "message LIKE :filter";
            $args[':filter'] = '%' . $filter . '%';
        }

        $where = implode(" $criteria ", $conditions);

        return ['where' => $where, 'args' => $args];
    }

    /**
     * Format a watchdog database row.
     *
     * @param $result
     *   A database result object.
     * @param $extended
     *   Return extended message details.
     * @return \stdClass
     *   The result object with some attributes themed.
     */
    protected function formatResult(\stdClass $result, bool $extended = false): \stdClass
    {
        // Severity.
        $severities = RfcLogLevel::getLevels();
        $result->severity = trim(DrupalUtil::drushRender($severities[$result->severity]));

        // Date.
        $result->date = date('d/M H:i', (int)$result->timestamp);
        unset($result->timestamp);

        // Username.
        $result->username = (new AnonymousUserSession())->getAccountName() ?: 'Anonymous';
        $account = User::load($result->uid);
        if ($account && !$account->isAnonymous()) {
            $result->username = $account->getAccountName();
        }

        // Message.
        $variables = $result->variables;
        if (is_string($variables)) {
            $variables = unserialize($variables);
        }
        if (is_array($variables)) {
            $result->message = strtr($result->message, $variables);
        }
        unset($result->variables);
        $message_length = 188;

        // Print all the data available
        if ($extended) {
            // Possible empty values.
            if (empty($result->link)) {
                unset($result->link);
            }
            if (empty($result->referer)) {
                unset($result->referer);
            }
            $message_length = PHP_INT_MAX;
        }
        $result->message = Unicode::truncate(strip_tags(Html::decodeEntities($result->message)), $message_length);

        return $result;
    }

    /**
     * Helper function to obtain the message types based on drupal version.
     *
     * @return array
     *   Array of watchdog message types.
     */
    public static function messageTypes(): array
    {
        return _dblog_get_message_types();
    }
}
