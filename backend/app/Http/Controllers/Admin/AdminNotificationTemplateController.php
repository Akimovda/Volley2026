<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminNotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = DB::table('notification_templates')
            ->orderBy('code')
            ->orderBy('channel')
            ->get();

        return view('admin.notification_templates.index', [
            'templates' => $templates,
        ]);
    }

    public function edit(int $template)
    {
        $row = DB::table('notification_templates')->where('id', $template)->first();
        abort_unless($row, 404);

        return view('admin.notification_templates.edit', [
            'template' => $row,
        ]);
    }

    public function update(Request $request, int $template)
    {
        $row = DB::table('notification_templates')->where('id', $template)->first();
        abort_unless($row, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_template' => ['nullable', 'string'],
            'body_template' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string'],
            'button_url_template' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('notification_templates')
            ->where('id', $template)
            ->update([
                'name' => $data['name'],
                'title_template' => $data['title_template'] ?? null,
                'body_template' => $data['body_template'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'button_text' => $data['button_text'] ?? null,
                'button_url_template' => $data['button_url_template'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Сохранено ✅');
    }
}
