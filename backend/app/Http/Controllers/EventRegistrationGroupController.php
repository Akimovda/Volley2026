<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventAccessService;
use App\Services\EventRegistrationGroupService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventRegistrationGroupController extends Controller
{
    public function __construct(
        private EventRegistrationGroupService $groupService,
        private EventAccessService $accessService
    ) {}

    public function create(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $this->groupService->createGroupForRegistration((int) $event->id, (int) $user->id);

            return back()->with('status', 'Группа создана.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось создать группу.');
        }
    }

    public function invite(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->groupService->inviteToGroup(
                (int) $event->id,
                (int) $user->id,
                (int) $data['to_user_id']
            );

            return back()->with('status', 'Приглашение отправлено.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось отправить приглашение.');
        }
    }

    public function accept(Request $request, Event $event, int $invite): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $this->groupService->acceptInvite($invite, (int) $user->id);

            return back()->with('status', 'Приглашение принято.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось принять приглашение.');
        }
    }

    public function decline(Request $request, Event $event, int $invite): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $this->groupService->declineInvite($invite, (int) $user->id);

            return back()->with('status', 'Приглашение отклонено.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось отклонить приглашение.');
        }
    }

    public function leave(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $this->groupService->leaveGroup((int) $event->id, (int) $user->id);

            return back()->with('status', 'Вы вышли из группы.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось выйти из группы.');
        }
    }

    public function managementInvite(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->accessService->ensureCanCreateEvents($user);

        $data = $request->validate([
            'from_user_id' => ['required', 'integer', 'min:1'],
            'to_user_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->groupService->inviteToGroup(
                (int) $event->id,
                (int) $data['from_user_id'],
                (int) $data['to_user_id']
            );

            return back()->with('status', 'Приглашение в группу отправлено.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось отправить приглашение.');
        }
    }
}
