<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

         // Поддержка @username
        if (mb_substr($q, 0, 1) === '@') {
            $q = trim(mb_substr($q, 1));
        }

        $authUser = $request->user();
        $isAdmin  = ($authUser?->role ?? null) === 'admin';

        // Явный фильтр ботов для контекста командных/групповых приглашений.
        // Frontend передаёт exclude_bots=1 на формах invite-в-команду / group-invite,
        // чтобы ботов нельзя было пригласить от имени игрока. Для остальных
        // контекстов (личное приглашение организатором, поиск тренера и т.п.)
        // боты остаются доступны.
        $excludeBots = $request->boolean('exclude_bots') && !$isAdmin;

        // Спецпоиск ботов по ключевому слову — недоступен если запрошен фильтр ботов
        if (in_array(mb_strtolower($q), ['bot', 'бот', 'боты', 'bots'], true)) {
            if ($excludeBots) {
                return response()->json(['ok' => true, 'items' => []]);
            }
            $items = DB::table('users')
                ->select(['id', 'first_name', 'last_name', 'name', 'telegram_username', 'is_bot'])
                ->where('is_bot', true)
                ->orderBy('last_name')->orderBy('first_name')
                ->limit(40)
                ->get()
                ->map(fn ($u) => $this->mapUserRow($u))
                ->values()->all();

            return response()->json(['ok' => true, 'items' => $items]);
        }
 
        // Фильтр по ролям (опционально)
        $rolesParam  = trim((string) $request->query('roles', ''));
        $rolesFilter = $rolesParam ? array_filter(array_map('trim', explode(',', $rolesParam))) : null;

        $variants = $this->buildSearchVariants($q);
        $likes = $this->buildLikePatterns($variants);

        $items = DB::table('users')
            ->select([
                'id',
                'first_name',
                'last_name',
                'name',
                'telegram_username',
                'is_bot',
                'role',
                'email',
            ])
            ->where(function ($q2) {
                $q2->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            // Командные/групповые приглашения: фильтр ботов по запросу клиента
            ->when($excludeBots, fn ($q2) => $q2->where(function ($w) {
                $w->whereNull('is_bot')->orWhere('is_bot', false);
            }))
            ->when($rolesFilter, fn($q2) => $q2->whereIn('role', $rolesFilter))
            ->where(function ($w) use ($likes, $q) {
                if (ctype_digit($q) && (int) $q > 0) {
                    $w->orWhere('id', (int) $q);
                }

                foreach ($likes as $like) {
                    $w->orWhere('first_name', 'ILIKE', $like)
                        ->orWhere('last_name', 'ILIKE', $like)
                        ->orWhereRaw("(coalesce(last_name, '') || ' ' || coalesce(first_name, '')) ILIKE ?", [$like])
                        ->orWhereRaw("(coalesce(first_name, '') || ' ' || coalesce(last_name, '')) ILIKE ?", [$like])
                        ->orWhere('name', 'ILIKE', $like)
                        ->orWhere('telegram_username', 'ILIKE', $like);
                }
            })
            ->orderByRaw("CASE WHEN coalesce(last_name, '') <> '' OR coalesce(first_name, '') <> '' THEN 0 ELSE 1 END")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($u) {
                $firstName = trim((string) ($u->first_name ?? ''));
                $lastName = trim((string) ($u->last_name ?? ''));
                $fullName = trim($lastName . ' ' . $firstName);
                $plainName = trim((string) ($u->name ?? ''));
                $telegramRaw = trim((string) ($u->telegram_username ?? ''));
                $telegram = $this->normalizeTelegramUsername($telegramRaw);

                $label = $fullName;

                if ($label === '' && $plainName !== '') {
                    $label = $plainName;
                }

                if ($label === '' && $telegram !== '') {
                    $label = $telegram;
                }

                if ($label === '') {
                    $label = '#' . $u->id;
                }

                $meta = [];

                if ($telegram !== '') {
                    $meta[] = $telegram;
                }

                if (
                    $fullName !== '' &&
                    $plainName !== '' &&
                    mb_strtolower($plainName) !== mb_strtolower($fullName)
                ) {
                    $meta[] = $plainName;
                }

                $metaStr = implode(' • ', array_filter($meta));

                return [
                    'id'               => (int) $u->id,
                    'label'            => $label,
                    'name'             => $label,
                    'full_name'        => $fullName,
                    'username'         => $telegram,
                    'telegram_username'=> $telegram,
                    'meta'             => $metaStr,
                    'sub'              => $metaStr,
                    'is_bot'           => (bool) ($u->is_bot ?? false),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }
    private function mapUserRow(object $u): array
    {
        $firstName = trim((string) ($u->first_name ?? ''));
        $lastName  = trim((string) ($u->last_name ?? ''));
        $fullName  = trim($lastName . ' ' . $firstName);
        $plainName = trim((string) ($u->name ?? ''));
        $telegram  = $this->normalizeTelegramUsername($u->telegram_username ?? null);
        $isBot     = (bool) ($u->is_bot ?? false);
 
        $label = $fullName ?: $plainName ?: $telegram ?: ('#' . $u->id);
 
        $meta = [];
        if ($telegram !== '') $meta[] = $telegram;
        if ($fullName !== '' && $plainName !== '' && mb_strtolower($plainName) !== mb_strtolower($fullName)) {
            $meta[] = $plainName;
        }
        if ($isBot) $meta[] = 'бот';
 
        return [
            'id'               => (int) $u->id,
            'label'            => $label,
            'name'             => $label,
            'full_name'        => $fullName,
            'username'         => $telegram,
            'telegram_username'=> $telegram,
            'meta'             => implode(' • ', array_filter($meta)),
            'sub'              => implode(' • ', array_filter($meta)),
            'is_bot'           => $isBot,
            'role'             => (string) ($u->role ?? 'user'),
            'email'            => (string) ($u->email ?? ''),
        ];
    }
    private function buildSearchVariants(string $q): array
    {
        $q2 = $this->ruToLat($q);

        return array_values(array_unique(array_filter([$q, $q2], function ($value) {
            return trim((string) $value) !== '';
        })));
    }

    private function buildLikePatterns(array $variants): array
    {
        $likes = [];

        foreach ($variants as $value) {
            $value = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
            $likes[] = '%' . $value . '%';
        }

        return $likes;
    }

    private function normalizeTelegramUsername(?string $username): string
    {
        $username = trim((string) $username);

        if ($username === '') {
            return '';
        }

        return '@' . ltrim($username, '@');
    }

    private function ruToLat(string $s): string
    {
        $map = [
            'а' => 'a',  'б' => 'b',   'в' => 'v',  'г' => 'g',   'д' => 'd',
            'е' => 'e',  'ё' => 'e',   'ж' => 'zh', 'з' => 'z',   'и' => 'i',
            'й' => 'y',  'к' => 'k',   'л' => 'l',  'м' => 'm',   'н' => 'n',
            'о' => 'o',  'п' => 'p',   'р' => 'r',  'с' => 's',   'т' => 't',
            'у' => 'u',  'ф' => 'f',   'х' => 'h',  'ц' => 'ts',  'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',   'ы' => 'y',   'ь' => '',
            'э' => 'e',  'ю' => 'yu',  'я' => 'ya',
        ];

        $out = '';
        $len = mb_strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $low = mb_strtolower($ch);
            $out .= $map[$low] ?? $ch;
        }

        return $out;
    }
}