<?php

namespace App\Support;

/**
 * Номер темы (топика) форума Telegram для отправки анонсов.
 *
 * У главной темы форума («General») технический номер 1, но Bot API на `message_thread_id=1` отвечает
 * «Bad Request: message thread not found». Чтобы написать в главную тему, параметр нужно НЕ передавать.
 * Поэтому «1», пустое значение, 0 и мусор — это «тема не задана» (null): запоминаем только настоящие темы (≥ 2).
 */
final class TelegramTopic
{
    public static function normalize(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 1 ? $id : null;
    }
}
