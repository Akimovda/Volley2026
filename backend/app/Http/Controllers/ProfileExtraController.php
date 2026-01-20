<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();
        $canEditProtected = $user->can('edit-protected-profile-fields');

        // ------------------------------------------------------------
        // Classic positions: допустимые значения (как задумано в миграции user_classic_positions)
        // ------------------------------------------------------------
        $classicAllowed = ['setter', 'outside', 'opposite', 'middle', 'libero'];

        // ------------------------------------------------------------
        // Helpers: "заполнено ли поле у пользователя"
        // ------------------------------------------------------------
        $filled = function ($value): bool {
            if (is_null($value)) return false;
            if (is_string($value)) return trim($value) !== '';
            return true;
        };

        // ------------------------------------------------------------
        // Protected fields: после первого заполнения редактирует только admin
        // ------------------------------------------------------------
        $protected = [
            'first_name', 'last_name', 'patronymic',
            'phone', 'birth_date', 'city_id',
            'classic_level', 'beach_level',
        ];

        // ------------------------------------------------------------
        // Определяем, какие поля "залочены" (disabled в форме => не придут в request)
        // ------------------------------------------------------------
        $locked = [
            'first_name'    => (!$canEditProtected && $filled($user->first_name)),
            'last_name'     => (!$canEditProtected && $filled($user->last_name)),
            'patronymic'    => (!$canEditProtected && $filled($user->patronymic)),
            'phone'         => (!$canEditProtected && $filled($user->phone)),
            'birth_date'    => (!$canEditProtected && $filled($user->birth_date)),
            'city_id'       => (!$canEditProtected && $filled($user->city_id)),
            'classic_level' => (!$canEditProtected && $filled($user->classic_level)),
            'beach_level'   => (!$canEditProtected && $filled($user->beach_level)),
        ];

        // ------------------------------------------------------------
        // 1) Нормализация входа ДО валидации
        //    - имена: латиница -> кириллица, TitleCase, только буквы/пробел/дефис
        //    - телефон: 8/7 -> +7, чистка
        // ------------------------------------------------------------
        $input = $request->all();

        if (!$locked['last_name'] && array_key_exists('last_name', $input)) {
            $input['last_name'] = $this->normalizeRuName((string)($input['last_name'] ?? ''));
        }
        if (!$locked['first_name'] && array_key_exists('first_name', $input)) {
            $input['first_name'] = $this->normalizeRuName((string)($input['first_name'] ?? ''));
        }
        if (!$locked['patronymic'] && array_key_exists('patronymic', $input)) {
            $input['patronymic'] = $this->normalizeRuName((string)($input['patronymic'] ?? ''));
        }
        if (!$locked['phone'] && array_key_exists('phone', $input)) {
            $input['phone'] = $this->normalizeRuPhone((string)($input['phone'] ?? ''));
        }

        // ------------------------------------------------------------
        // 2) Валидация
        //    ВАЖНО: если поле залочено и disabled — оно не придет в request,
        //    поэтому ставим rules "sometimes" для залоченных полей.
        // ------------------------------------------------------------

        // Regex для ФИО:
        // - первая буква заглавная
        // - далее строчные
        // - допускаем составные: "Иванов-Петров", "Анна Мария"
        // - каждая часть минимум 2 символа
        $ruNameRegex = '/^[А-ЯЁ][а-яё]{1,}(?:[ -][А-ЯЁ][а-яё]{1,})*$/u';

        // Телефон E.164 RU: +7 + 10 цифр
        $ruPhoneRegex = '/^\+7\d{10}$/';

        $rules = [
            // ------------------------------
            // "фиксируемые" поля
            // ------------------------------
            'first_name' => $locked['first_name']
                ? ['sometimes']
                : ['required', 'string', 'min:2', 'max:255', "regex:$ruNameRegex"],

            'last_name' => $locked['last_name']
                ? ['sometimes']
                : ['required', 'string', 'min:2', 'max:255', "regex:$ruNameRegex"],

            'patronymic' => $locked['patronymic']
                ? ['sometimes']
                : ['required', 'string', 'min:2', 'max:255', "regex:$ruNameRegex"],

            'phone' => $locked['phone']
                ? ['sometimes']
                : ['required', 'string', 'max:50', "regex:$ruPhoneRegex"],

            'birth_date' => $locked['birth_date']
                ? ['sometimes']
                : ['nullable', 'date'],

            'city_id' => $locked['city_id']
                ? ['sometimes']
                : ['nullable', 'exists:cities,id'],

            'classic_level' => $locked['classic_level']
                ? ['sometimes']
                : ['nullable', 'integer'],

            'beach_level' => $locked['beach_level']
                ? ['sometimes']
                : ['nullable', 'integer'],

            // ------------------------------
            // "не фиксируемые" поля
            // ------------------------------
            'gender'    => ['nullable', 'in:m,f'],
            'height_cm' => ['nullable', 'integer', 'min:40', 'max:230'],

            // ------------------------------
            // классика: амплуа
            // ------------------------------
            'classic_primary_position'  => ['nullable', 'in:' . implode(',', $classicAllowed)],
            'classic_extra_positions'   => ['nullable', 'array'],
            'classic_extra_positions.*' => ['nullable', 'in:' . implode(',', $classicAllowed)],

            // ------------------------------
            // пляж: режим зоны/универсал
            // ------------------------------
            'beach_mode' => ['nullable', 'in:2,4,universal'],
        ];

        $messages = [
            'last_name.required'    => 'Фамилия обязательна.',
            'first_name.required'   => 'Имя обязательно.',
            'patronymic.required'   => 'Отчество обязательно.',
            'phone.required'        => 'Телефон обязателен.',

            'last_name.regex'       => 'Фамилия: кириллица, минимум 2 символа, первая буква заглавная.',
            'first_name.regex'      => 'Имя: кириллица, минимум 2 символа, первая буква заглавная.',
            'patronymic.regex'      => 'Отчество: кириллица, минимум 2 символа, первая буква заглавная.',
            'phone.regex'           => 'Телефон: формат +7XXXXXXXXXX (E.164, Россия).',
        ];

        $data = Validator::make($input, $rules, $messages)->validate();

        // ------------------------------------------------------------
        // 3) Защита: если не admin — запрещаем менять уже заполненные protected поля
        // ------------------------------------------------------------
        if (!$canEditProtected) {
            foreach ($protected as $field) {
                if ($filled($user->$field)) {
                    unset($data[$field]);
                }
            }
        }

        // ------------------------------------------------------------
        // 4) Правило уровней по возрасту (если меняем уровень — проверяем)
        // ------------------------------------------------------------
        $birth = $data['birth_date'] ?? $user->birth_date;
        if (!empty($birth)) {
            $age = Carbon::parse($birth)->age;
            $allowed = $age < 18 ? [1, 2, 4] : [1, 2, 3, 4, 5, 6, 7];

            foreach (['classic_level', 'beach_level'] as $lvl) {
                if (array_key_exists($lvl, $data) && !is_null($data[$lvl])) {
                    if (!in_array((int)$data[$lvl], $allowed, true)) {
                        return back()
                            ->withErrors([$lvl => 'Недопустимый уровень для вашего возраста.'])
                            ->withInput();
                    }
                }
            }
        }

        // ------------------------------------------------------------
        // 5) “Виртуальные” поля формы не должны попадать в users
        // ------------------------------------------------------------
        unset(
            $data['classic_primary_position'],
            $data['classic_extra_positions'],
            $data['beach_mode']
        );

        // ------------------------------------------------------------
        // 6) Save in transaction
        // ------------------------------------------------------------
        DB::transaction(function () use ($request, $user, $data, $classicAllowed) {
            // 6.1) Сохраняем обычные поля users
            $user->forceFill($data)->save();

            // ======================================================
            // 6.2) КЛАССИКА: primary + extras (полная пересборка)
            // ======================================================
            $primary = $request->input('classic_primary_position');
            $extrasRaw = $request->input('classic_extra_positions', null);
            $extras = is_array($extrasRaw) ? $extrasRaw : [];

            // Нормализация extras: уникальные + только разрешенные
            $extras = array_values(array_unique(array_filter($extras, function ($p) use ($classicAllowed) {
                return in_array($p, $classicAllowed, true);
            })));

            // primary не должен быть в extras
            if (!empty($primary)) {
                $extras = array_values(array_filter($extras, fn ($p) => $p !== $primary));
            }

            // Пересобираем только если пользователь реально прислал данные про амплуа
            $shouldRebuildClassic = !empty($primary) || is_array($extrasRaw);

            if ($shouldRebuildClassic) {
                $user->classicPositions()->delete();

                if (!empty($primary)) {
                    $user->classicPositions()->create([
                        'position'   => $primary,
                        'is_primary' => true,
                    ]);
                }

                foreach ($extras as $p) {
                    $user->classicPositions()->create([
                        'position'   => $p,
                        'is_primary' => false,
                    ]);
                }
            }

            // ======================================================
            // 6.3) ПЛЯЖ: mode (полная пересборка зон) + beach_universal
            // ======================================================
            $mode = $request->input('beach_mode');

            // Если mode не прислали — вообще не трогаем зоны и beach_universal
            if (!empty($mode)) {
                $user->beachZones()->delete();

                if ($mode === 'universal') {
                    $user->forceFill(['beach_universal' => true])->save();

                    // записываем 2 и 4; primary = 2 по умолчанию
                    $user->beachZones()->create(['zone' => 2, 'is_primary' => true]);
                    $user->beachZones()->create(['zone' => 4, 'is_primary' => false]);
                } elseif ($mode === '2' || $mode === '4') {
                    $user->forceFill(['beach_universal' => false])->save();

                    $zone = (int)$mode;
                    $user->beachZones()->create(['zone' => $zone, 'is_primary' => true]);
                }
            }
        });

        return back()->with('status', 'Профиль обновлён ✅');
    }

    // =========================================================
    // Helpers: normalize RU names & phone
    // =========================================================

    /**
     * RU name normalization:
     * - trim, collapse spaces
     * - latin -> cyrillic (basic translit)
     * - keep only cyrillic letters + space + hyphen
     * - TitleCase each part (split by space/hyphen)
     */
    private function normalizeRuName(string $value): string
    {
        $v = trim(preg_replace('/\s+/u', ' ', $value));

        // If contains latin letters -> translit
        if (preg_match('/[A-Za-z]/', $v)) {
            $v = $this->latinToCyrillicRu($v);
        }

        // Remove all non-cyrillic symbols except space and hyphen
        $v = preg_replace('/[^А-Яа-яЁё -]/u', '', $v);
        $v = trim(preg_replace('/\s+/u', ' ', $v));

        // TitleCase each part
        $parts = preg_split('/([ -])/u', $v, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';

        foreach ($parts as $p) {
            if ($p === ' ' || $p === '-') {
                $out .= $p;
                continue;
            }
            $p = mb_strtolower($p, 'UTF-8');
            $first = mb_substr($p, 0, 1, 'UTF-8');
            $rest  = mb_substr($p, 1, null, 'UTF-8');
            $out .= mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return $out;
    }

    /**
     * Phone normalization to E.164 RU:
     * - keep digits and '+'
     * - 8XXXXXXXXXX -> +7XXXXXXXXXX
     * - 7XXXXXXXXXX -> +7XXXXXXXXXX
     * - keep +7XXXXXXXXXX as is
     */
    private function normalizeRuPhone(string $value): string
    {
        $v = trim($value);
        $v = preg_replace('/[^\d+]/', '', $v);

        if (preg_match('/^8(\d{10})$/', $v, $m)) {
            return '+7' . $m[1];
        }
        if (preg_match('/^7(\d{10})$/', $v, $m)) {
            return '+7' . $m[1];
        }
        return $v;
    }

    /**
     * Basic transliteration latin -> cyrillic (good enough for names).
     * We do not try to be perfect linguistic translit; goal: UX.
     */
    private function latinToCyrillicRu(string $s): string
    {
        $map = [
            // multi-letter first
            'sch' => 'щ', 'sh' => 'ш', 'ch' => 'ч', 'zh' => 'ж', 'kh' => 'х', 'ts' => 'ц', 'yo' => 'ё', 'yu' => 'ю', 'ya' => 'я',

            // single letters
            'a'=>'а','b'=>'б','v'=>'в','g'=>'г','d'=>'д','e'=>'е','z'=>'з','i'=>'и','j'=>'й','k'=>'к','l'=>'л','m'=>'м','n'=>'н','o'=>'о','p'=>'п','r'=>'р','s'=>'с','t'=>'т','u'=>'у','f'=>'ф','h'=>'х','y'=>'ы',
            'q'=>'к','w'=>'в','x'=>'кс',
        ];

        $v = $s;
        $lower = mb_strtolower($v, 'UTF-8');

        // replace multi-letter first
        foreach (['sch','sh','ch','zh','kh','ts','yo','yu','ya'] as $k) {
            $lower = str_replace($k, $map[$k], $lower);
        }

        // replace single latin letters
        $lower = preg_replace_callback('/[a-z]/', function ($m) use ($map) {
            return $map[$m[0]] ?? $m[0];
        }, $lower);

        return $lower;
    }
}
