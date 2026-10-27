<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Маскирование токенов Telegram-ботов (`bot<id>:<секрет>` и «голый» `<id>:<секрет>`) в логах и событиях GlitchTip/Sentry.
 * Текст исключений Guzzle/cURL содержит полный URL запроса вместе с токеном — без маски он попадает в laravel.log и в GlitchTip.
 */
class SecretMasker
{
    /** id бота 8–12 цифр, секрет 30+ символов [A-Za-z0-9_-]; префикс `bot` сохраняется. */
    private const PATTERN = '/(?<![A-Za-z0-9_])(bot)?\d{8,12}:[A-Za-z0-9_-]{30,}/';

    public static function mask(string $text): string
    {
        if ($text === '' || !str_contains($text, ':')) {
            return $text;
        }

        return (string) preg_replace_callback(self::PATTERN, fn ($m) => ($m[1] ?? '') . '<токен>', $text);
    }

    /** Рекурсивно по массиву (контекст, extra, данные breadcrumb). */
    public static function maskDeep(mixed $v): mixed
    {
        if (is_string($v)) {
            return self::mask($v);
        }
        if (is_array($v)) {
            foreach ($v as $k => $item) {
                $v[$k] = self::maskDeep($item);
            }
        }

        return $v;
    }

    /** Sentry `before_send` (callable-массив, чтобы config:cache работал). */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        try {
            if ($m = $event->getMessage()) {
                $event->setMessage(self::mask($m), $event->getMessageParams(), $event->getMessageFormatted() !== null ? self::mask((string) $event->getMessageFormatted()) : null);
            }
            foreach ($event->getExceptions() as $bag) {
                $bag->setValue(self::mask((string) $bag->getValue()));
            }
            $event->setBreadcrumb(array_map(fn (Breadcrumb $b) => self::breadcrumb($b), $event->getBreadcrumbs()));
            $event->setExtra(self::maskDeep($event->getExtra()));
            $event->setTags(self::maskDeep($event->getTags()));
            if ($req = $event->getRequest()) {
                $event->setRequest(self::maskDeep($req));
            }
            if ($tx = $event->getTransaction()) {
                $event->setTransaction(self::mask($tx));
            }
        } catch (\Throwable) {
            // маскировка не должна ронять отправку события
        }

        return $event;
    }

    /** Sentry `before_breadcrumb`: логи и HTTP-клиент кладут URL с токеном в данные breadcrumb. */
    public static function beforeBreadcrumb(Breadcrumb $b): ?Breadcrumb
    {
        return self::breadcrumb($b);
    }

    private static function breadcrumb(Breadcrumb $b): Breadcrumb
    {
        if ($b->getMessage() !== null) {
            $b = $b->withMessage(self::mask($b->getMessage()));
        }
        foreach ($b->getMetadata() as $k => $v) {
            $b = $b->withMetadata((string) $k, self::maskDeep($v));
        }

        return $b;
    }
}
