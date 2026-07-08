<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Автогенерация названия команды, когда организатор/автораспределение
 * не задали имя явно. Пляж — фамилии игроков через "/", классика —
 * случайное короткое название (важно только для tournament_individual,
 * где нет естественного "имени пары/команды" от капитана).
 */
class TournamentTeamNamingService
{
    private const ADJECTIVES = [
        'Дикие', 'Атомные', 'Бешеные', 'Летучие', 'Стальные', 'Огненные', 'Ледяные',
        'Ловкие', 'Хитрые', 'Быстрые', 'Смелые', 'Крутые', 'Ужасные', 'Весёлые',
        'Могучие', 'Шальные', 'Юркие', 'Заряженные', 'Пиратские', 'Королевские',
    ];

    private const NOUNS = [
        'Бобры', 'Ежи', 'Волки', 'Тигры', 'Орлы', 'Акулы', 'Медведи', 'Соколы',
        'Барсуки', 'Драконы', 'Носороги', 'Пантеры', 'Гепарды', 'Скорпионы',
        'Вараны', 'Пираньи', 'Совы', 'Викинги', 'Мамонты', 'Единороги',
    ];

    /**
     * @param Collection<int, User> $members
     */
    public function generate(Event $event, Collection $members, ?int $occurrenceId): string
    {
        $direction = (string) ($event->direction ?? 'classic');

        $name = $direction === 'beach'
            ? $this->fromSurnames($members)
            : $this->randomFunnyName();

        return $this->ensureUnique($event, $occurrenceId, $name, $direction);
    }

    private function fromSurnames(Collection $members): string
    {
        $names = $members
            ->map(fn (User $u) => trim($u->last_name ?: $u->first_name ?: $u->name ?: ('Игрок' . $u->id)))
            ->filter()
            ->values();

        return $names->isNotEmpty() ? $names->implode('/') : 'Команда';
    }

    private function randomFunnyName(): string
    {
        $adj = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
        $noun = self::NOUNS[array_rand(self::NOUNS)];

        return "{$adj} {$noun}";
    }

    private function ensureUnique(Event $event, ?int $occurrenceId, string $name, string $direction): string
    {
        $base = $name;
        $attempt = 0;

        while (
            EventTeam::where('event_id', $event->id)
                ->where('occurrence_id', $occurrenceId)
                ->where('name', $name)
                ->exists()
        ) {
            $attempt++;

            if ($attempt > 50) {
                $name = $base . ' #' . random_int(1000, 9999);
                break;
            }

            $name = $direction === 'beach'
                ? $base . ' (' . ($attempt + 1) . ')'
                : $this->randomFunnyName();
        }

        return $name;
    }
}
