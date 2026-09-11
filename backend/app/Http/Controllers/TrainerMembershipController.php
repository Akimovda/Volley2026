<?php

namespace App\Http\Controllers;

use App\Models\SchoolTrainer;
use App\Services\SchoolTrainerService;
use Illuminate\Http\Request;

class TrainerMembershipController extends Controller
{
    private function authorizeOwn(Request $request, SchoolTrainer $membership): void
    {
        abort_unless((int) $membership->user_id === (int) $request->user()->id, 403);
    }

    public function confirm(Request $request, SchoolTrainer $membership, SchoolTrainerService $service)
    {
        $this->authorizeOwn($request, $membership);
        abort_unless($membership->status === SchoolTrainer::STATUS_PENDING, 422);

        $service->confirm($membership);

        return back()->with('status', __('trainers.membership_confirmed'));
    }

    public function decline(Request $request, SchoolTrainer $membership, SchoolTrainerService $service)
    {
        $this->authorizeOwn($request, $membership);
        abort_unless($membership->status === SchoolTrainer::STATUS_PENDING, 422);

        $service->decline($membership);

        return back()->with('status', __('trainers.membership_declined'));
    }
}
