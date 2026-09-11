<?php

namespace App\Services;

use App\Models\SchoolTrainer;
use App\Models\User;
use App\Models\VolleyballSchool;

class SchoolTrainerService
{
    public function __construct(private UserNotificationService $notificationService) {}

    public function invite(VolleyballSchool $school, int $userId, int $invitedByUserId): SchoolTrainer
    {
        $membership = SchoolTrainer::updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $userId],
            [
                'invited_by_user_id' => $invitedByUserId,
                'status'             => SchoolTrainer::STATUS_PENDING,
                'invited_at'         => now(),
                'confirmed_at'       => null,
                'removed_at'         => null,
            ]
        );

        $this->notificationService->createTrainerAssignedNotification(
            userId: $userId,
            schoolId: $school->id,
            schoolName: $school->name,
        );

        return $membership;
    }

    public function confirm(SchoolTrainer $membership): SchoolTrainer
    {
        $membership->update([
            'status'       => SchoolTrainer::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        return $membership;
    }

    public function decline(SchoolTrainer $membership): SchoolTrainer
    {
        $membership->update(['status' => SchoolTrainer::STATUS_DECLINED]);

        return $membership;
    }

    public function setPermissions(SchoolTrainer $membership, array $permissions): SchoolTrainer
    {
        $membership->update([
            'can_manage_schedule'      => (bool) ($permissions['can_manage_schedule'] ?? false),
            'can_manage_registrations' => (bool) ($permissions['can_manage_registrations'] ?? false),
            'can_view_analytics'       => (bool) ($permissions['can_view_analytics'] ?? false),
        ]);

        return $membership;
    }

    public function remove(SchoolTrainer $membership): SchoolTrainer
    {
        $membership->update([
            'status'     => SchoolTrainer::STATUS_REMOVED,
            'removed_at' => now(),
        ]);

        return $membership;
    }
}
