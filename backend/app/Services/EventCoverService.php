<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class EventCoverService
{
    /**
     * Обработка обложки мероприятия
     */
    public function resolveCover(Request $request): array
    {
        $file = $request->file('cover_upload');
        $mediaId = (int) $request->input('cover_media_id', 0);

        /*
        |--------------------------------------------------------------------------
        | NOTHING PROVIDED
        |--------------------------------------------------------------------------
        */

        if (!$file && !$mediaId) {
            return [
                'file' => null,
                'media_id' => null,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | FILE VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($file) {

            $validator = Validator::make(
                ['cover_upload' => $file],
                [
                    'cover_upload' => [
                        'file',
                        'image',
                        'mimes:jpg,jpeg,png,webp',
                        'max:5120',
                    ]
                ]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages(
                    $validator->errors()->toArray()
                );
            }

            return [
                'file' => $file,
                'media_id' => null
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | MEDIA LIBRARY
        |--------------------------------------------------------------------------
        */

        if ($mediaId > 0) {
            return [
                'file' => null,
                'media_id' => $mediaId
            ];
        }

        return [
            'file' => null,
            'media_id' => null
        ];
    }
}
