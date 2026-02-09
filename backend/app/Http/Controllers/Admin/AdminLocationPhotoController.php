<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AdminLocationPhotoController extends Controller
{
    /**
     * Сохранение порядка фото (Drag&Drop)
     */
    public function reorder(Request $request, Location $location)
    {
        $data = $request->validate([
            'photo_ids'   => ['required', 'array'],
            'photo_ids.*' => ['integer'],
        ]);

        $ids = array_values($data['photo_ids']);

        foreach ($ids as $order => $mediaId) {
            Media::query()
                ->where('id', $mediaId)
                ->where('model_type', Location::class)
                ->where('model_id', $location->id)
                ->update(['order_column' => $order + 1]);
        }

        return back()->with('status', 'Порядок фото сохранён ✅');
    }

    /**
     * Удаление одного фото
     */
    public function destroy(Location $location, Media $media)
    {
        abort_unless(
            $media->model_type === Location::class
            && (int)$media->model_id === (int)$location->id,
            403
        );

        $media->delete();

        return back()->with('status', 'Фото удалено ✅');
    }
}
