<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Тренерский профиль теперь редактируется в общей форме профиля
 * (/profile/complete, блок «Я тренер»). Старый адрес оставлен как редирект,
 * чтобы закладки, письма и уведомления продолжали работать.
 */
class TrainerProfileController extends Controller
{
    public function edit(Request $request)
    {
        return redirect()->to('/profile/complete?section=trainer#trainer');
    }
}
