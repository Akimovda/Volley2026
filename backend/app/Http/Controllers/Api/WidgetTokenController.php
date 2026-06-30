<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WidgetTokenController extends Controller
{
    public function issue(Request $request)
    {
        $user = $request->user();

        $user->tokens()->where('name', 'ios_widget')->delete();

        $token = $user->createToken('ios_widget', ['activity:read'])->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}
