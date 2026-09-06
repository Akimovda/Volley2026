<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    /** Человекочитаемые короткие названия действий (action -> label) */
    private const ACTION_LABELS = [
        'user.role.update' => 'Изменение роли',
        'user.club_manager.update' => 'Статус «менеджер клуба»',
        'user.restriction.set' => 'Установка ограничения',
        'user.restriction.clear' => 'Снятие ограничений',
        'impersonate.start' => 'Вход от имени пользователя',
        'impersonate.leave' => 'Выход из режима имперсонации',
        'user.delete.purge' => 'Полное удаление пользователя',
    ];

    /** Человекочитаемые названия типов цели (target_type -> label) */
    private const TARGET_TYPE_LABELS = [
        'user' => 'Пользователь',
    ];

    public function index(Request $request)
    {
        $filters = [
            'action' => trim((string) $request->get('action', '')),
            'admin_user_id' => trim((string) $request->get('admin_user_id', '')),
            'target_type' => trim((string) $request->get('target_type', '')),
            'target_id' => trim((string) $request->get('target_id', '')),
            'date_from' => trim((string) $request->get('date_from', '')),
            'date_to' => trim((string) $request->get('date_to', '')),
        ];

        $builder = DB::table('admin_audits')
            ->leftJoin('users as admins', 'admins.id', '=', 'admin_audits.admin_user_id')
            ->select([
                'admin_audits.*',
                'admins.name as admin_name',
                'admins.email as admin_email',
            ])
            ->when($filters['action'] !== '', fn ($q) => $q->where('admin_audits.action', $filters['action']))
            ->when($filters['admin_user_id'] !== '', fn ($q) => $q->where('admin_audits.admin_user_id', (int) $filters['admin_user_id']))
            ->when($filters['target_type'] !== '', fn ($q) => $q->where('admin_audits.target_type', $filters['target_type']))
            ->when($filters['target_id'] !== '', fn ($q) => $q->where('admin_audits.target_id', $filters['target_id']))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('admin_audits.created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('admin_audits.created_at', '<=', $filters['date_to']))
            ->orderByDesc('admin_audits.id');

        $audits = $builder->paginate(50)->withQueryString();

        $audits->getCollection()->transform(function ($a) {
            $metaArr = $this->decodeMeta($a->meta);
            [$label, $detail] = $this->humanizeAction((string) $a->action, $metaArr);

            $a->action_label = $label;
            $a->action_detail = $detail;
            $a->target_label = self::TARGET_TYPE_LABELS[$a->target_type] ?? ($a->target_type ?: '—');

            return $a;
        });

        $actionOptions = DB::table('admin_audits')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(fn ($action) => [$action => self::ACTION_LABELS[$action] ?? $action])
            ->all();

        $targetTypeOptions = DB::table('admin_audits')
            ->select('target_type')
            ->whereNotNull('target_type')
            ->distinct()
            ->orderBy('target_type')
            ->pluck('target_type')
            ->mapWithKeys(fn ($type) => [$type => self::TARGET_TYPE_LABELS[$type] ?? $type])
            ->all();

        return view('admin.audits.index', [
            'audits' => $audits,
            'filters' => $filters,
            'actionOptions' => $actionOptions,
            'targetTypeOptions' => $targetTypeOptions,
        ]);
    }

    private function decodeMeta(mixed $meta): ?array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @return array{0: string, 1: string} [краткое название, человекочитаемая детализация] */
    private function humanizeAction(string $action, ?array $meta): array
    {
        $label = self::ACTION_LABELS[$action] ?? $action;
        $note = is_array($meta) ? ($meta['note'] ?? null) : null;

        $detail = match ($action) {
            'user.role.update' => sprintf('%s → %s', $meta['old_role'] ?? '—', $meta['new_role'] ?? '—'),
            'user.club_manager.update' => sprintf(
                '%s → %s',
                $this->boolLabel($meta['old_is_club_manager'] ?? null),
                $this->boolLabel($meta['new_is_club_manager'] ?? null)
            ),
            'user.restriction.set' => $this->describeRestrictionSet($meta),
            'user.restriction.clear' => 'удалено ограничений: ' . ($meta['deleted_count'] ?? 0),
            'impersonate.start' => trim(($meta['impersonated_name'] ?? '') . ' (#' . ($meta['impersonated_user_id'] ?? '?') . ')'),
            'impersonate.leave' => '#' . ($meta['impersonated_user_id'] ?? '?'),
            'user.delete.purge' => (string) ($meta['email'] ?? ''),
            default => $this->describeGeneric($meta),
        };

        if ($note) {
            $detail = trim($detail . ($detail !== '' ? ' — ' : '') . $note);
        }

        return [$label, $detail];
    }

    private function boolLabel(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value ? 'да' : 'нет';
    }

    private function describeRestrictionSet(?array $meta): string
    {
        if (!$meta) {
            return '';
        }

        $parts = [];

        if (!empty($meta['is_global'])) {
            $parts[] = 'глобально (все мероприятия)';
        } elseif (!empty($meta['event_ids']) && is_array($meta['event_ids'])) {
            $parts[] = 'мероприятий: ' . count($meta['event_ids']);
        }

        $parts[] = !empty($meta['ends_at']) ? ('до ' . $meta['ends_at']) : 'бессрочно';

        if (isset($meta['deleted_registrations'])) {
            $parts[] = 'отменено записей: ' . $meta['deleted_registrations'];
        }

        return implode(', ', $parts);
    }

    private function describeGeneric(?array $meta): string
    {
        if (!$meta) {
            return '';
        }

        $parts = [];

        foreach ($meta as $key => $value) {
            if ($key === 'note') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_bool($value)) {
                $value = $this->boolLabel($value);
            }

            $parts[] = "{$key}: {$value}";
        }

        return implode(', ', $parts);
    }
}
