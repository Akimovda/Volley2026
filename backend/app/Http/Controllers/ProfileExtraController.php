<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();
        $canEditProtected = $user->can('edit-protected-profile-fields');

        $data = $request->validate([
            // фиксируемые поля
            'first_name'    => ['nullable', 'string', 'max:255'],
            'last_name'     => ['nullable', 'string', 'max:255'],
            'patronymic'    => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'birth_date'    => ['nullable', 'date'],
            'city_id'       => ['nullable', 'exists:cities,id'],
            'classic_level' => ['nullable', 'integer'],
            'beach_level'   => ['nullable', 'integer'],

            // НЕ фиксируемые: игрок может менять
            'gender'        => ['nullable', 'in:m,f'],
            'height_cm'     => ['nullable', 'integer', 'min:40', 'max:230'],
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
                        return back()->withErrors([$lvl => 'Недопустимый уровень для вашего возраста.'])->withInput();
                    }
                }
            }
        }

        $user->forceFill($data)->save();

        return back()->with('status', 'Профиль обновлён.');
    }
}
