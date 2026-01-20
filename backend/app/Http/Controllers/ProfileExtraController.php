<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $canEditProtected = $user->can('edit-protected-profile-fields');

        // =========================
        // 0) Pre-normalize input (server-side)
        // =========================
        $normalized = [];

        // ФИО: автотранслит латиницы -> кириллица + "С Заглавной"
        foreach (['first_name', 'last_name', 'patronymic'] as $k) {
            if ($request->has($k)) {
                $normalized[$k] = $this->normalizeCyrName($request->input($k));
            }
        }

        // Телефон: берём либо phone (hidden E.164), либо phone_masked (если JS выключен)
        if ($request->hasAny(['phone', 'phone_masked'])) {
            $rawPhone = $request->input('phone');
            if (empty($rawPhone)) {
                $rawPhone = $request->input('phone_masked');
            }
            $normalized['phone'] = $this->normalizeRuPhoneToE164($rawPhone);
        }

        // Закинем нормализованное обратно в request ДО validate()
        if (!empty($normalized)) {
            $request->merge($normalized);
        }

        // =========================
        // 1) Validate
        // =========================
        $classicAllowed = ['setter', 'outside', 'opposite', 'middle', 'libero'];

        $data = $request->validate([
            // защищаемые (после заполнения редактирует только admin)
            'first_name'  => ['nullable', 'string', 'min:2', 'max:255', 'regex:/^[А-Яа-яЁё \-\'’]+$/u'],
            'last_name'   => ['nullable', 'string', 'min:2', 'max:255', 'regex:/^[А-Яа-яЁё \-\'’]+$/u'],
            'patronymic'  => ['nullable', 'string', 'min:2', 'max:255', 'regex:/^[А-Яа-яЁё \-\'’]+$/u'],
            'phone'       => ['nullable', 'string', 'regex:/^\+7\d{10}$/'], // E.164 RU: +7XXXXXXXXXX
            'birth_date'  => ['nullable', 'date'],
            'city_id'     => ['nullable', 'exists:cities,id'],
            'classic_level' => ['nullable', 'integer'],
            'beach_level'   => ['nullable', 'integer'],

            // незащищаемые (пользователь может менять)
            'gender'    => ['nullable', 'in:m,f'],
            'height_cm' => ['nullable', 'integer', 'min:40', 'max:230'],

            // "виртуальные" поля формы
            'classic_primary_position'  => ['nullable', 'in:' . implode(',', $classicAllowed)],
            'classic_extra_positions'   => ['nullable', 'array'],
            'classic_extra_positions.*' => ['nullable', 'in:' . implode(',', $classicAllowed)],
            'beach_mode'                => ['nullable', 'in:2,4,universal'],

            // если JS выключен и прислали phone_masked — пусть не падает
            'phone_masked' => ['nullable', 'string', 'max:50'],
        ], [
            'first_name.regex' => 'Имя должно быть на кириллице (разрешены пробел/дефис).',
            'last_name.regex'  => 'Фамилия должна быть на кириллице (разрешены пробел/дефис).',
            'patronymic.regex' => 'Отчество должно быть на кириллице (разрешены пробел/дефис).',
            'phone.regex'      => 'Телефон должен быть в формате +7XXXXXXXXXX (11 цифр после +7).',
        ]);

        // =========================
        // 2) Protect fields after first fill (if not admin)
        // =========================
        $protected = [
            'first_name', 'last_name', 'patronymic',
            'phone', 'birth_date', 'city_id',
            'classic_level', 'beach_level',
        ];

        if (!$canEditProtected) {
            foreach ($protected as $field) {
                if (!empty($user->$field)) {
                    unset($data[$field]);
                }
            }
        }

        // =========================
        // 3) Age-based level rule (server truth)
        // =========================
        $birth = $data['birth_date'] ?? $user->birth_date;
        if (!empty($birth)) {
            $age = Carbon::parse($birth)->age;
            $allowed = $age < 18 ? [1, 2, 4] : [1, 2, 3, 4, 5, 6, 7];

            foreach (['classic_level', 'beach_level'] as $lvl) {
                if (array_key_exists($lvl, $data) && !is_null($data[$lvl])) {
                    if (!in_array((int) $data[$lvl], $allowed, true)) {
                        return back()
                            ->withErrors([$lvl => 'Недопустимый уровень для вашего возраста.'])
                            ->withInput();
                    }
                }
            }
        }

        // =========================
        // 4) Remove virtual fields (not in users table)
        // =========================
        unset(
            $data['classic_primary_position'],
            $data['classic_extra_positions'],
            $data['beach_mode'],
            $data['phone_masked'],
        );

        // =========================
        // 5) Save (users + relations)
        // =========================
        DB::transaction(function () use ($request, $user, $data, $classicAllowed) {
            // 5.1 users
            $user->forceFill($data)->save();

            // 5.2 Classic positions rebuild
            $primary = $request->input('classic_primary_position');
            $extrasRaw = $request->input('classic_extra_positions', null);
            $extras = is_array($extrasRaw) ? $extrasRaw : [];

            $extras = array_values(array_unique(array_filter($extras, function ($p) use ($classicAllowed) {
                return in_array($p, $classicAllowed, true);
            })));

            if (!empty($primary)) {
                $extras = array_values(array_filter($extras, fn ($p) => $p !== $primary));
            }

            $shouldRebuildClassic = !empty($primary) || is_array($extrasRaw);
            if ($shouldRebuildClassic) {
                $user->classicPositions()->delete();

                if (!empty($primary)) {
                    $user->classicPositions()->create([
                        'position' => $primary,
                        'is_primary' => true,
                    ]);
                }

                foreach ($extras as $p) {
                    $user->classicPositions()->create([
                        'position' => $p,
                        'is_primary' => false,
                    ]);
                }
            }

            // 5.3 Beach zones rebuild + beach_universal
            $mode = $request->input('beach_mode');

            if (!empty($mode)) {
                $user->beachZones()->delete();

                if ($mode === 'universal') {
                    $user->forceFill(['beach_universal' => true])->save();

                    $user->beachZones()->create(['zone' => 2, 'is_primary' => true]);
                    $user->beachZones()->create(['zone' => 4, 'is_primary' => false]);
                } elseif ($mode === '2' || $mode === '4') {
                    $user->forceFill(['beach_universal' => false])->save();

                    $zone = (int) $mode;
                    $user->beachZones()->create(['zone' => $zone, 'is_primary' => true]);
                }
            }
        });

        return back()->with('status', 'Профиль обновлён.');
    }

    // =========================================================
    // Helpers: ФИО
    // =========================================================
    private function normalizeCyrName(?string $value): ?string
    {
        if ($value === null) return null;

        $s = trim($value);
        if ($s === '') return null;

        // если есть латиница — транслитим
        if (preg_match('/[A-Za-z]/', $s)) {
            $s = $this->translitLatinToCyr($s);
        }

        // нормализуем пробелы/разделители
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace("/[’]/u", "'", $s);
        $s = preg_replace("/-{2,}/u", "-", $s);

        // оставляем только кириллицу + пробел/дефис/апостроф
        $s = preg_replace("/[^А-Яа-яЁё \-']/u", '', $s);

        // "С Заглавной" по сегментам (пробел/дефис/апостроф)
        $parts = preg_split("/(\s+|-|')/u", $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $p) {
            if ($p === '' || $p === ' ' || $p === '-' || $p === "'" || preg_match('/^\s+$/u', $p)) {
                $out .= $p;
                continue;
            }
            $p = mb_strtolower($p, 'UTF-8');
            $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8');
        }

        $out = trim($out);
        return $out === '' ? null : $out;
    }

    private function translitLatinToCyr(string $s): string
    {
        $map = [
            'sch' => 'щ', 'yo' => 'ё', 'zh' => 'ж', 'kh' => 'х', 'ts' => 'ц', 'ch' => 'ч',
            'sh'  => 'ш', 'yu' => 'ю', 'ya' => 'я',

            'a'=>'а','b'=>'б','v'=>'в','g'=>'г','d'=>'д','e'=>'е','z'=>'з','i'=>'и','j'=>'й','k'=>'к',
            'l'=>'л','m'=>'м','n'=>'н','o'=>'о','p'=>'п','r'=>'р','s'=>'с','t'=>'т','u'=>'у','f'=>'ф',
            'h'=>'х','c'=>'к','y'=>'ы','w'=>'в','q'=>'к','x'=>'кс',
        ];

        $lower = mb_strtolower($s, 'UTF-8');
        $out = '';
        $len = strlen($lower);

        // работаем посимвольно (ASCII-латиница), остальные символы оставляем
        for ($i = 0; $i < $len; $i++) {
            $ch = $lower[$i];

            if (!preg_match('/[a-z]/', $ch)) {
                $out .= $s[$i];
                continue;
            }

            $tri = substr($lower, $i, 3);
            $bi  = substr($lower, $i, 2);

            if (isset($map[$tri])) { $out .= $map[$tri]; $i += 2; continue; }
            if (isset($map[$bi]))  { $out .= $map[$bi];  $i += 1; continue; }
            if (isset($map[$ch]))  { $out .= $map[$ch]; continue; }

            $out .= $ch;
        }

        return $out;
    }

    // =========================================================
    // Helpers: Телефон
    // =========================================================
    private function normalizeRuPhoneToE164(?string $raw): ?string
    {
        if ($raw === null) return null;

        $digits = preg_replace('/\D+/', '', $raw ?? '');
        if ($digits === '') return null;

        // 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        // 7XXXXXXXXXX -> +7XXXXXXXXXX
        if (strlen($digits) === 11 && str_starts_with($digits, '7')) {
            return '+7' . substr($digits, 1);
        }

        // XXXXXXXXXX -> +7XXXXXXXXXX
        if (strlen($digits) === 10) {
            return '+7' . $digits;
        }

        // любой другой случай — вернём "+..." чтобы не потерять, но validate отфейлит
        return '+' . $digits;
    }
}
