<?php

namespace App\Services;

use App\Models\UserNotificationChannel;
use App\Support\TelegramTopic;
use Illuminate\Support\Facades\Cache;

/**
 * Проверка настроек Telegram-канала/группы/темы, чтобы организатор видел, что настроил всё правильно, ДО публикации.
 * Что проверяем: бот видит чат → у бота есть права писать → тема (если указана) существует и в чате вообще есть темы.
 *
 * Существование темы Bot API напрямую не отдаёт. sendChatAction/editForumTopic на несуществующую тему отвечают «ok»,
 * зато reopenForumTopic различает: несуществующая → TOPIC_ID_INVALID, существующая → «нет прав»/TOPIC_NOT_MODIFIED.
 * Если у бота есть право управлять темами, reopen мог бы реально открыть закрытую тему — тогда вместо него
 * шлём тихое сообщение и сразу удаляем.
 *
 * Результат: ['level' => ok|warn|error, 'message' => текст].
 */
class TelegramChannelChecker
{
    public function check(UserNotificationChannel $channel, mixed $rawThread = null): array
    {
        if ($channel->platform !== 'telegram') {
            return $this->res('warn', __('channel_check.not_telegram'));
        }

        $token = $channel->resolveToken() ?: (string) config('services.telegram.bot_token');
        if ($token === '') {
            return $this->res('error', __('channel_check.no_token'));
        }
        $chatId = (string) $channel->chat_id;
        $thread = TelegramTopic::normalize($rawThread);
        $general = is_numeric($rawThread) && (int) $rawThread === 1;

        try {
            $chat = $this->call($token, 'getChat', ['chat_id' => $chatId]);
            if (!($chat['ok'] ?? false)) {
                return $this->res('error', __('channel_check.chat_not_found'));
            }
            $info    = $chat['result'] ?? [];
            $type    = (string) ($info['type'] ?? '');
            $isForum = !empty($info['is_forum']);

            $botId  = $this->botId($token);
            $member = $botId ? $this->call($token, 'getChatMember', ['chat_id' => $chatId, 'user_id' => $botId]) : null;
            $m      = ($member['ok'] ?? false) ? ($member['result'] ?? []) : [];
            $status = (string) ($m['status'] ?? '');

            if (in_array($status, ['left', 'kicked'], true) || ($member && !($member['ok'] ?? false))) {
                return $this->res('error', __('channel_check.bot_not_member'));
            }
            if ($type === 'channel' && !($status === 'creator' || (!empty($m['can_post_messages'])))) {
                return $this->res('error', __('channel_check.bot_cannot_post_channel'));
            }
            if ($status === 'restricted' && isset($m['can_send_messages']) && !$m['can_send_messages']) {
                return $this->res('error', __('channel_check.bot_restricted'));
            }

            if ($general) {
                return $this->res('warn', __('channel_check.general_topic'));
            }

            if ($thread === null) {
                return $isForum
                    ? $this->res('ok', __('channel_check.ok_forum_main'))
                    : $this->res('ok', __('channel_check.ok'));
            }

            if (!$isForum) {
                return $this->res('error', __('channel_check.not_forum', ['n' => $thread]));
            }

            $exists = $this->topicExists($token, $chatId, $thread, !empty($m['can_manage_topics']) || $status === 'creator');
            if ($exists === false) {
                return $this->res('error', __('channel_check.topic_not_found', ['n' => $thread]));
            }
            if ($exists === null) {
                return $this->res('warn', __('channel_check.topic_unknown', ['n' => $thread]));
            }

            return $this->res('ok', __('channel_check.ok_topic', ['n' => $thread]));
        } catch (\Throwable $e) {
            return $this->res('warn', __('channel_check.unavailable'));
        }
    }

    /** true — тема есть, false — нет, null — выяснить не удалось. */
    private function topicExists(string $token, string $chatId, int $thread, bool $canManageTopics): ?bool
    {
        if (!$canManageTopics) {
            $r = $this->call($token, 'reopenForumTopic', ['chat_id' => $chatId, 'message_thread_id' => $thread]);
            $d = strtolower((string) ($r['description'] ?? ''));
            if (str_contains($d, 'topic_id_invalid') || str_contains($d, 'thread not found')) {
                return false;
            }
            if (($r['ok'] ?? false) || str_contains($d, 'not enough rights') || str_contains($d, 'topic_not_modified')) {
                return true;
            }
            return null;
        }

        // у бота есть право на темы — не трогаем тему, а проверяем тихим сообщением (сразу удаляем)
        $r = $this->call($token, 'sendMessage', [
            'chat_id' => $chatId, 'message_thread_id' => $thread, 'text' => '✅', 'disable_notification' => true,
        ]);
        if ($r['ok'] ?? false) {
            $mid = $r['result']['message_id'] ?? null;
            if ($mid) {
                $this->call($token, 'deleteMessage', ['chat_id' => $chatId, 'message_id' => $mid]);
            }
            return true;
        }
        $d = strtolower((string) ($r['description'] ?? ''));
        return str_contains($d, 'thread not found') ? false : null;
    }

    private function botId(string $token): ?int
    {
        return Cache::remember('tg_bot_id_' . md5($token), 3600, function () use ($token) {
            $me = $this->call($token, 'getMe', []);
            return ($me['ok'] ?? false) ? (int) $me['result']['id'] : null;
        });
    }

    private function call(string $token, string $method, array $params): array
    {
        return TelegramApi::http(10)->post(TelegramApi::url($token, $method), $params)->json() ?: [];
    }

    private function res(string $level, string $message): array
    {
        return ['level' => $level, 'message' => $message];
    }
}
