<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();
        $canEditProtected = $user->can('edit-protected-profile-fields');

        // допустимые значения (как задумано в миграции user_classic_positions)
        $classicAllowed = ['setter', 'outside', 'opposite', 'middle', 'libero'];

        $data = $request->validate([
            // фиксируемые поля (после заполнения редактирует только admin)
            'first_name'    => ['nullable', 'string', 'max:255'],
            'last_name'     => ['nullable', 'string', 'max:255'],
            'patronymic'    => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'birth_date'    => ['nullable', 'date'],
            'city_id'       => ['nullable', 'exists:cities,id'],
            'classic_level' => ['nullable', 'integer'],
            'beach_level'   => ['nullable', 'integer'],

            // НЕ фиксируемые (игрок может менять)
            'gender'        => ['nullable', 'in:m,f'],
            'height_cm'     => ['nullable', 'integer', 'min:40', 'max:230'],

            // КЛАССИКА: амплуа
            'classic_primary_position'      => ['nullable', 'in:' . implode(',', $classicAllowed)],
            'classic_extra_positions'       => ['nullable', 'array'],
            'classic_extra_positions.*'     => ['nullable', 'in:' . implode(',', $classicAllowed)],

            // ПЛЯЖ: режим зоны/универсал
            'beach_mode' => ['nullable', 'in:2,4,universal'],
        ]);

        // Поля, которые "защищаем" после первого заполнения (если не admin)
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

        // Правило уровней по возрасту (если меняем уровень, проверяем)
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

        // “Виртуальные” поля формы не должны попадать в users
        unset(
            $data['classic_primary_position'],
            $data['classic_extra_positions'],
            $data['beach_mode']
        );

        DB::transaction(function () use ($request, $user, $data, $classicAllowed) {
            // 1) сохраняем обычные поля users
            $user->forceFill($data)->save();

            // =======================
            // 2) КЛАССИКА: primary + extras (полная пересборка)
            // =======================
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
            // (то есть primary выбран ИЛИ extras реально пришли массивом)
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

            // =======================
            // 3) ПЛЯЖ: mode (полная пересборка зон) + beach_universal
            // =======================
            $mode = $request->input('beach_mode');

            // Важно: если mode не прислали — вообще не трогаем ни зоны, ни beach_universal
            if (!empty($mode)) {
                $user->beachZones()->delete();

                if ($mode === 'universal') {
                    $user->forceFill(['beach_universal' => true])->save();

                    // записываем 2 и 4; primary = 2 по умолчанию
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
}
