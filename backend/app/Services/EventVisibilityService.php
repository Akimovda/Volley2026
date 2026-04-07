<?php
// app/Services/EventVisibilityService.php
namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventVisibilityService
{
    public function isPrivateEventRow(Event $event): bool
    {
        $isPrivate = false;

        if (Schema::hasColumn('events', 'is_private')) {
            $isPrivate = $isPrivate || ((int) ($event->is_private ?? 0) === 1);
        }

        if (Schema::hasColumn('events', 'visibility')) {
            $isPrivate = $isPrivate || ((string) ($event->visibility ?? '') === 'private');
        }

        return $isPrivate;
    }

    public function canViewPrivateEvent(Event $event, ?User $user): bool
    {
        if (!$this->isPrivateEventRow($event)) return true;
            // ✅ Доступ по публичному токену — для незалогиненных
        $requestToken = request()->route('token') ?? request()->query('token');
        if (
            $requestToken &&
            !empty($event->public_token) &&
            $event->public_token === $requestToken
        ) {
            return true;
        }
   
        if (!$user) return false;

        $role = strtolower(trim((string) ($user->role ?? 'user')));
        $uid  = (int) $user->id;

        if (in_array($role, ['admin', 'superadmin', 'owner', 'root'], true)) {
            return true;
        }

        $eventOrgId = Schema::hasColumn('events', 'organizer_id')
            ? (int) ($event->organizer_id ?? 0)
            : 0;

        if ($eventOrgId > 0 && $eventOrgId === $uid) {
            return true;
        }

        if ($role === 'staff' && $eventOrgId > 0) {
            $orgIds = $this->staffOrganizerIds($uid);
            if (in_array($eventOrgId, $orgIds, true)) {
                return true;
            }
        }

        $ownerUserIds = [$uid];

        if ($role !== 'staff') {
            $ownerUserIds = array_values(array_unique(array_merge(
                $ownerUserIds,
                $this->organizerStaffUserIds($uid)
            )));
        }

        foreach (['created_by', 'creator_user_id', 'created_user_id'] as $col) {
            if (!Schema::hasColumn('events', $col)) continue;

            $cid = (int) ($event->{$col} ?? 0);
            if ($cid > 0 && in_array($cid, $ownerUserIds, true)) {
                return true;
            }
        }

        return false;
    }

    public function applyPrivateVisibilityScope($q, ?User $user, string $prefix = ''): void
    {
        $hasIsPrivate  = Schema::hasColumn('events', 'is_private');
        $hasVisibility = Schema::hasColumn('events', 'visibility');

        if (!$hasIsPrivate && !$hasVisibility) return;

        if ($user) {
            $role = strtolower(trim((string) ($user->role ?? 'user')));
            if (in_array($role, ['admin', 'superadmin', 'owner', 'root'], true)) {
                return;
            }
        }

        $q->where(function ($w) use ($user, $hasIsPrivate, $hasVisibility, $prefix) {

            $w->where(function ($pub) use ($hasIsPrivate, $hasVisibility, $prefix) {

                if ($hasIsPrivate) {
                    $pub->where(function ($x) use ($prefix) {
                        $x->where($prefix.'is_private', 0)
                          ->orWhereNull($prefix.'is_private');
                    });
                }

                if ($hasVisibility) {
                    $pub->where(function ($x) use ($prefix) {
                        $x->where($prefix.'visibility', '!=', 'private')
                          ->orWhereNull($prefix.'visibility');
                    });
                }

            });

            if ($user) {

                $role = strtolower(trim((string) ($user->role ?? 'user')));
                $uid  = (int) $user->id;

                if (Schema::hasColumn('events', 'organizer_id')) {

                    $w->orWhere($prefix.'organizer_id', $uid);

                    if ($role === 'staff') {
                        $orgIds = $this->staffOrganizerIds($uid);

                        if (!empty($orgIds)) {
                            $w->orWhereIn($prefix.'organizer_id', $orgIds);
                        }
                    }
                }

                $ownerUserIds = [$uid];

                if ($role !== 'staff') {
                    $ownerUserIds = array_values(array_unique(array_merge(
                        $ownerUserIds,
                        $this->organizerStaffUserIds($uid)
                    )));
                }

                foreach (['created_by','creator_user_id','created_user_id'] as $col) {

                    if (Schema::hasColumn('events',$col)) {
                        $w->orWhereIn($prefix.$col,$ownerUserIds);
                    }

                }

            }

        });
    }

    public function applyPrivateVisibilityNegationScope($q, ?User $user, string $prefix = ''): void
    {
        if (!$user) return;

        $role = strtolower(trim((string) ($user->role ?? 'user')));
        $uid  = (int) $user->id;

        if (in_array($role, ['admin','superadmin','owner','root'], true)) {
            $q->whereRaw('1=0');
            return;
        }

        $q->where(function ($w) use ($role,$uid,$prefix) {

            if (Schema::hasColumn('events','organizer_id')) {

                $w->where($prefix.'organizer_id','!=',$uid);

                if ($role === 'staff') {

                    $orgIds = $this->staffOrganizerIds($uid);

                    if (!empty($orgIds)) {
                        $w->whereNotIn($prefix.'organizer_id',$orgIds);
                    }

                }

            }

            if ($role === 'staff') {

                foreach (['created_by','creator_user_id','created_user_id'] as $col) {

                    if (Schema::hasColumn('events',$col)) {
                        $w->where($prefix.$col,'!=',$uid);
                    }

                }

            }

        });
    }

   private function organizerStaffUserIds(int $organizerId): array
    {
        $candidates = [
            ['org'=>'organizer_id','staff'=>'staff_user_id'],
            ['org'=>'organizer_id','staff'=>'user_id'],
            ['org'=>'organizer_id','staff'=>'staff_id'],
            ['org'=>'organizer_id','staff'=>'staff_user'],
        ];
    
        foreach ($candidates as $c) {
    
            if (!Schema::hasTable('organizer_staff')) continue;
            if (!Schema::hasColumn('organizer_staff',$c['org'])) continue;
            if (!Schema::hasColumn('organizer_staff',$c['staff'])) continue;
    
            return DB::table('organizer_staff')
                ->where($c['org'],$organizerId)
                ->pluck($c['staff'])
                ->map(fn($v)=>(int)$v)
                ->filter(fn($v)=>$v>0)
                ->unique()
                ->values()
                ->all();
        }
    
        return [];
    }
    
    public function isStaffOfOrganizer(int $staffUserId, int $organizerId): bool
    {
        $ids = $this->staffOrganizerIds($staffUserId);
        return in_array($organizerId, $ids, true);
    }
    private function staffOrganizerIds(int $staffUserId): array
    {
        $candidates = [
            ['org'=>'organizer_id','staff'=>'staff_user_id'],
            ['org'=>'organizer_id','staff'=>'user_id'],
            ['org'=>'organizer_id','staff'=>'staff_id'],
            ['org'=>'organizer_id','staff'=>'staff_user'],
        ];
    
        foreach ($candidates as $c) {
    
            if (!Schema::hasTable('organizer_staff')) continue;
            if (!Schema::hasColumn('organizer_staff',$c['org'])) continue;
            if (!Schema::hasColumn('organizer_staff',$c['staff'])) continue;
    
            return DB::table('organizer_staff')
                ->where($c['staff'],$staffUserId)
                ->pluck($c['org'])
                ->map(fn($v)=>(int)$v)
                ->unique()
                ->values()
                ->all();
        }
    
        if (Schema::hasColumn('users','organizer_id')) {
    
            $orgId = (int) DB::table('users')
                ->where('id',$staffUserId)
                ->value('organizer_id');
    
            return $orgId > 0 ? [$orgId] : [];
        }
    
        return [];
    }
    
}