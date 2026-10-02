<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Убирает из галерей заглушку Яндекса («серый силуэт», islands-200.png 42×42, 1536 байт), которую раньше сохраняли
 * как фото/аватар пользователям без фото в Яндексе. По умолчанию — ТОЛЬКО показ (dry-run); удаление — с --force.
 *
 *  - медиа заглушки удаляется (файлы и конверсии тоже);
 *  - если она была аватаром — аватаром становится другое фото пользователя из галереи (самое старое), иначе аватар
 *    сбрасывается (сайт покажет инициалы).
 */
class CleanupYandexDefaultAvatars extends Command
{
    protected $signature = 'users:cleanup-yandex-default-avatars {--force : Действительно удалить (без флага — только показать)}';
    protected $description = 'Удалить заглушку аватара Яндекса из галерей пользователей (по умолчанию — dry-run)';

    /** md5 заглушки (islands-200.png, 42×42, 1536 байт) — сверяем и размер, и хэш, чтобы не задеть настоящие фото. */
    private const PLACEHOLDER_MD5 = 'ba4fdd0fd76496bb792cb8f14067e3bd';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $rows = Media::query()
            ->where('collection_name', 'photos')
            ->where('model_type', User::class)
            ->where('file_name', 'islands-200.png')
            ->where('size', 1536)
            ->get();

        $placeholders = $rows->filter(function (Media $m) {
            $path = $m->getPath();
            return is_file($path) && md5_file($path) === self::PLACEHOLDER_MD5;
        });

        $this->info("Найдено заглушек Яндекса: {$placeholders->count()} (из {$rows->count()} кандидатов по имени/размеру)");

        $asAvatar = 0; $replaced = 0; $reset = 0; $deleted = 0;

        foreach ($placeholders as $m) {
            $user = User::withTrashed()->find($m->model_id);
            if (!$user) { continue; }

            $isAvatar = (int) $user->avatar_media_id === (int) $m->id;
            $replacement = null;
            if ($isAvatar) {
                $asAvatar++;
                $replacement = Media::query()
                    ->where('collection_name', 'photos')->where('model_type', User::class)->where('model_id', $user->id)
                    ->where('id', '!=', $m->id)->orderBy('id')->first();
            }

            $this->line(sprintf('  user #%d media #%d%s%s', $user->id, $m->id,
                $isAvatar ? ' [был аватаром → ' . ($replacement ? "фото #{$replacement->id}" : 'сброс (инициалы)') . ']' : '',
                $force ? '' : '  (dry-run)'));

            if (!$force) { continue; }

            DB::transaction(function () use ($user, $m, $isAvatar, $replacement, &$replaced, &$reset, &$deleted) {
                if ($isAvatar) {
                    $user->avatar_media_id = $replacement?->id;
                    $user->save();
                    $replacement ? $replaced++ : $reset++;
                }
                $m->delete(); // Spatie: удаляет файл и конверсии
                $deleted++;
            });
        }

        $this->newLine();
        $this->info("Были аватаром: {$asAvatar}" . ($force ? " | заменено другим фото: {$replaced} | сброшено: {$reset} | удалено медиа: {$deleted}" : ' | режим dry-run: ничего не изменено (запуск с --force удалит)'));

        return self::SUCCESS;
    }
}
