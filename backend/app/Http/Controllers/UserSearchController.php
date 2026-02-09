<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        // поддержка @nickname
        if (mb_substr($q, 0, 1) === '@') {
            $q = trim(mb_substr($q, 1));
        }

        $hasNickname = Schema::hasColumn('users', 'nickname');
        $hasUsername = Schema::hasColumn('users', 'username');

        $cols = ['id', 'name', 'email'];
        if ($hasNickname) $cols[] = 'nickname';
        if ($hasUsername) $cols[] = 'username';

        $q2 = $this->ruToLat($q); // "акимов" -> "akimov"
        $variants = array_values(array_unique(array_filter([$q, $q2], function ($v) {
            return trim((string)$v) !== '';
        })));

        // экранируем LIKE
        $likes = [];
        foreach ($variants as $v) {
            $v = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v);
            $likes[] = '%' . $v . '%';
        }

        $query = DB::table('users')->select($cols);

        $query->where(function ($w) use ($likes, $hasNickname, $hasUsername, $q) {
            // поиск по id, если ввели число
            if (ctype_digit($q) && (int)$q > 0) {
                $w->orWhere('id', (int)$q);
            }

            foreach ($likes as $like) {
                $w->orWhere('name', 'like', $like)
                  ->orWhere('email', 'like', $like);

                if ($hasNickname) $w->orWhere('nickname', 'like', $like);
                if ($hasUsername) $w->orWhere('username', 'like', $like);
            }
        });

        $items = $query
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($u) use ($hasNickname, $hasUsername) {
                $label = trim((string)($u->name ?? ''));
                if ($label === '' && $hasNickname) $label = (string)($u->nickname ?? '');
                if ($label === '' && $hasUsername) $label = (string)($u->username ?? '');
                if ($label === '') $label = (string)($u->email ?? ('#' . $u->id));

                $meta = [];
                if ($hasNickname && !empty($u->nickname)) $meta[] = '@' . $u->nickname;
                elseif ($hasUsername && !empty($u->username)) $meta[] = '@' . $u->username;
                if (!empty($u->email)) $meta[] = (string)$u->email;

                $metaStr = implode(' • ', array_filter($meta));

                return [
                    'id'    => (int)$u->id,
                    'label' => $label,
                    'meta'  => $metaStr, // под твой фронт
                    'sub'   => $metaStr, // совместимость
                ];
            })
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    private function ruToLat(string $s): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];

        $out = '';
        $len = mb_strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $ch  = mb_substr($s, $i, 1);
            $low = mb_strtolower($ch);

            if (isset($map[$low])) {
                $out .= $map[$low];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
