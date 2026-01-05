<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventRegistrationRequirements;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();
        $canEdit = $user->can('edit-protected-profile-fields');

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'classic_level' => ['nullable', 'integer'],
            'beach_level' => ['nullable', 'integer'],
        ]);

        $protected = [
            'first_name','last_name','patronymic',
            'phone','birth_date','classic_level','beach_level'
        ];

        if (!$canEdit) {
            foreach ($protected as $field) {
                if (!empty($user->$field)) {
                    unset($data[$field]);
                }
            }
        }

        $birth = $data['birth_date'] ?? $user->birth_date;
        if ($birth) {
            $age = Carbon::parse($birth)->age;
            $allowed = $age < 18 ? [1,2,4] : [1,2,3,4,5,6,7];

            foreach (['classic_level','beach_level'] as $lvl) {
                if (isset($data[$lvl]) && !in_array($data[$lvl], $allowed, true)) {
                    return back()->withErrors([$lvl => 'Недопустимый уровень']);
                }
            }
        }

        $user->forceFill($data)->save();

        return back()->with('status', 'Профиль обновлён.');
    }
}
