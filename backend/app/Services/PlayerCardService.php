<?php

namespace App\Services;

use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Карточки игроков для пульта/расстановки: фото (thumb аватара — ОДИН запрос на медиа, без N+1),
 * инициалы для случая «нет фото» (рисуются на цвете команды, без внешних сервисов), номер на футболке.
 */
class PlayerCardService
{
    public function __construct(private JerseyNumberService $numbers) {}

    /** @return array<int, array{id:int, surname:string, first:string, initials:string, photo:?string, number:?int}> */
    public function cards(array $userIds, ?int $eventId = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'name', 'avatar_media_id']);
        $mediaIds = $users->pluck('avatar_media_id')->filter()->unique()->values()->all();
        $media = $mediaIds ? Media::whereIn('id', $mediaIds)->get()->keyBy('id') : collect();
        $numbers = $eventId ? $this->numbers->forEvent($eventId, $userIds) : [];

        $out = [];
        foreach ($users as $u) {
            $m = $u->avatar_media_id ? $media->get($u->avatar_media_id) : null;
            $photo = null;
            if ($m && $m->hasGeneratedConversion('thumb')) {
                $photo = $m->getUrl('thumb');
            }
            $surname = trim((string) ($u->last_name ?: ''));
            $first = trim((string) ($u->first_name ?: ''));
            if ($surname === '' && $first === '') {
                [$first, $surname] = array_pad(explode(' ', trim((string) $u->name), 2), 2, '');
            }
            $out[(int) $u->id] = [
                'id'       => (int) $u->id,
                'surname'  => $surname !== '' ? $surname : $first,
                'first'    => $surname !== '' ? $first : '',
                'initials' => mb_strtoupper(mb_substr($surname !== '' ? $surname : $first, 0, 1) . ($surname !== '' ? mb_substr($first, 0, 1) : '')),
                'photo'    => $photo,
                'number'   => $numbers[(int) $u->id] ?? null,
            ];
        }

        return $out;
    }

    /** Цвет текста (#fff/#111) для подписи на фоне цвета команды. */
    public static function textOn(string $hex): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) !== 6) {
            return '#fff';
        }
        $lum = (0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2))) / 255;

        return $lum > 0.72 ? '#111' : '#fff';
    }
}
