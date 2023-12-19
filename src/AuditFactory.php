<?php

namespace Springtimesoft\AuditLogger;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Factory;

/**
 * Logs are written using a side-channel, because audit trail should not be mixed
 * up with regular PHP errors.
 */
class AuditFactory implements Factory
{
    use Configurable;

    /**
     * Defines the audit log relative to vendor/
     *
     * @var string
     */
    private static $auditLog = '../public/assets/audit.log';

    /**
     * Defines the log level
     *
     * Value should be one of the following:
     * debug|info|notice|warning|error|critical|alert|emergency
     *
     * @var string
     */
    private static $logLevel = 'info';

    /**
     * Defines how many days to keep audit logs for - auto-pruned
     *
     * @var int
     */
    private static $keepForDays = 30;

    /**
     * Create the service
     *
     * @param [type] $service
     *
     * @return mixed
     */
    public function create($service, array $params = [])
    {
        if (!empty($params)) {
            throw new \Exception('AuditFactory does not support passing params.');
        }

        $c           = Config::inst();
        $logFile     = $c->get(__CLASS__, 'auditLog');
        $logLevel    = $c->get(__CLASS__, 'logLevel');
        $keepForDays = $c->get(__CLASS__, 'keepForDays');

        switch ($service) {
            case 'AuditLogger':
                $this->truncateLog($logFile, $keepForDays);

                $log    = new Logger('audit');
                $syslog = new StreamHandler($logFile, $logLevel);

                $syslog->pushProcessor(new WebProcessor($_SERVER, [
                    'ip'          => 'REMOTE_ADDR',
                    'url'         => 'REQUEST_URI',
                    'http_method' => 'REQUEST_METHOD',
                    'referrer'    => 'HTTP_REFERER',
                ]));

                $formatter = new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n");
                $syslog->setFormatter($formatter);

                $log->pushHandler($syslog);

                return $log;

            default:
                throw new \Exception(sprintf("AuditFactory does not support creation of '%s'.", $service));
        }
    }

    /**
     * Auto-truncate the audit log if exists.
     *
     * This reads/writes the file in a memory-efficient manner, and locks the $logFile
     * to prevent two processes overwriting the log at the same time
     *
     * @param string $logFile     Log file
     * @param int    $keepForDays Number of days to keep
     *
     * @return void
     */
    private function truncateLog($logFile, $keepForDays)
    {
        if ($keepForDays < 1 || !file_exists($logFile)) {
            return;
        }

        // get last modified date
        $mtime = filemtime($logFile);

        // skip if file can't be opened or modification date is today
        if (!$mtime || date('Y-m-d', $mtime) == date('Y-m-d')) {
            return;
        }

        $tmpFile = tmpfile();

        if ($file = fopen($logFile, 'r')) {
            while (!feof($file)) {
                $line = fgets($file);
                if ($line && preg_match('/^\[(\d\d\d\d\-\d\d\-\d\d)/', $line, $matches)) {
                    $daysAgo = floor((time() - strtotime($matches[1])) / (60 * 60 * 24));

                    if ($daysAgo <= $keepForDays) {
                        fwrite($tmpFile, $line);
                    }
                }
            }
            fclose($file);

            // rewind temp file to beginning
            fseek($tmpFile, 0);

            // open the original log and transfer the output from the temp file
            $fp = fopen($logFile, 'w');
            if (flock($fp, LOCK_EX)) {
                while (!feof($tmpFile)) {
                    $line = fgets($tmpFile);
                    fwrite($fp, $line);
                }

                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }
}
