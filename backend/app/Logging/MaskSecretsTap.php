<?php

namespace App\Logging;

use App\Support\SecretMasker;
use Illuminate\Log\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/** `tap` для каналов логов: токены ботов маскируются в контексте и в итоговой строке (включая трассировку исключений). */
class MaskSecretsTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        $monolog->pushProcessor(function (LogRecord $r): LogRecord {
            return $r->with(message: SecretMasker::mask($r->message), context: SecretMasker::maskDeep($r->context));
        });
        foreach ($monolog->getHandlers() as $handler) {
            if (method_exists($handler, 'setFormatter')) {
                $handler->setFormatter(new class($handler->getFormatter()) implements FormatterInterface {
                    public function __construct(private FormatterInterface $inner) {}

                    public function format(LogRecord $record): mixed
                    {
                        $out = $this->inner->format($record);

                        return is_string($out) ? SecretMasker::mask($out) : $out;
                    }

                    public function formatBatch(array $records): mixed
                    {
                        return array_map(fn ($r) => $this->format($r), $records);
                    }
                });
            }
        }
    }
}
