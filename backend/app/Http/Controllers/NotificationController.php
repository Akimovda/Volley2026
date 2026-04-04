<?php
	
	namespace App\Http\Controllers;
	
	use App\Models\UserNotification;
	use App\Services\UserNotificationService;
	use DomainException;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\View\View;
	
	class NotificationController extends Controller
	{
		public function __construct(
        private UserNotificationService $userNotificationService
		) {}
		
		public function index(Request $request): View
		{
			$user = $request->user();
			
			$notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(20);
			
			$unreadCount = UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
			
			return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
			]);
		}
		
		public function read(Request $request, int $notification): RedirectResponse
		{
			$user = $request->user();
			
			try {
				$this->userNotificationService->markAsRead($notification, (int) $user->id);
				
				return back()->with('status', 'Уведомление отмечено как прочитанное.');
				} catch (DomainException $e) {
				return back()->with('error', $e->getMessage());
				} catch (\Throwable $e) {
				report($e);
				
				return back()->with('error', 'Не удалось отметить уведомление как прочитанное.');
			}
		}
		
		public function readAll(Request $request): RedirectResponse
		{
			$user = $request->user();
			
			try {
				$this->userNotificationService->markAllAsRead((int) $user->id);
				
				return back()->with('status', 'Все уведомления отмечены как прочитанные.');
				} catch (\Throwable $e) {
				report($e);
				
				return back()->with('error', 'Не удалось отметить уведомления как прочитанные.');
			}
		}
		
		public function destroy(Request $request, int $notification): RedirectResponse
		{
			$user = $request->user();
			
			try {
				$this->userNotificationService->delete($notification, (int) $user->id);
				
				return back()->with('status', 'Уведомление удалено.');
				} catch (DomainException $e) {
				return back()->with('error', $e->getMessage());
				} catch (\Throwable $e) {
				report($e);
				
				return back()->with('error', 'Не удалось удалить уведомление.');
			}
		}	
		
	}
