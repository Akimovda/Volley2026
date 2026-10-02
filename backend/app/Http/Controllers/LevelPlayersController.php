<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * /level_players — описание уровней игроков.
 *  ?scope=spb|standard — терминология (по умолчанию — по городу зрителя); так страница-окно из /profile/complete
 *                        показывает названия под ВЫБРАННЫЙ в форме город, ещё не сохранённый в профиле;
 *  ?embed=1            — облегчённая версия для всплывающего окна (iframe), без шапки сайта.
 */
class LevelPlayersController extends Controller
{
    public function __invoke(Request $request)
    {
        $scope = in_array($request->query('scope'), ['spb', 'standard'], true) ? $request->query('scope') : null;

        if ($request->boolean('embed')) {
            return view('pages.level_players_embed', [
                'levelScope' => $scope ?? level_terminology_scope_for_user($request->user()),
            ]);
        }

        return view('pages.level_players', ['scope' => $scope]);
    }
}
