<?php
	
	namespace App\Http\Controllers;
	
	use App\Models\User;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Spatie\MediaLibrary\MediaCollections\Models\Media;
	
	class UserPhotoController extends Controller
	{ 
		public function index(Request $request)
		{
			/** @var User $user */
			$user = $request->user();
			
			// Поддержка редактирования админом
			$editingUserId = $request->get('user_id');
			if ($editingUserId && auth()->user()?->isAdmin()) {
				$editingUser = User::find($editingUserId);
				if ($editingUser) {
					$user = $editingUser;
				}
			}
			
			// Обычная галерея - коллекция 'photos'
			$photos = $user->getMedia('photos')
            ->sortByDesc('created_at')
            ->values();
			
			// Фото для мероприятий - коллекция 'event_photos'
			$eventPhotos = $user->getMedia('event_photos')
            ->sortByDesc('created_at')
            ->values();
			
			return view('user.photos', [
            'user'        => $user,
            'photos'      => $photos,
            'eventPhotos' => $eventPhotos,
			]);
		}
		
		public function store(Request $request)
		{
			try {
				// 1. Проверка наличия файлов
				if (!$request->hasFile('photo_original') || !$request->hasFile('photo_cropped')) {
					return response()->json([
                    'success' => false,
                    'error' => 'Не переданы оба файла (оригинал и квадрат)'
					], 422);
				}
				
				$originalFile = $request->file('photo_original');
				$croppedFile = $request->file('photo_cropped');
				
				// 2. Проверка размера
				if ($originalFile->getSize() > 15 * 1024 * 1024) {
					return response()->json([
                    'success' => false,
                    'error' => 'Оригинальный файл слишком большой. Максимум 15 МБ'
					], 422);
				}
				
				if ($croppedFile->getSize() > 5 * 1024 * 1024) {
					return response()->json([
                    'success' => false,
                    'error' => 'Квадрат слишком большой. Максимум 5 МБ'
					], 422);
				}
				
				// 3. Проверка MIME-типов
				$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
				
				if (!in_array($originalFile->getMimeType(), $allowedMimes)) {
					return response()->json([
                    'success' => false,
                    'error' => 'Оригинал должен быть в формате JPEG, PNG или WEBP'
					], 422);
				}
				
				// 4. Определяем пользователя
				$userId = $request->input('user_id');
				if ($userId && auth()->user()?->isAdmin()) {
					$user = User::find($userId);
					if (!$user) {
						return response()->json(['success' => false, 'error' => 'Пользователь не найден'], 404);
					}
					} else {
					$user = $request->user();
				}
				
				// 5. Определяем коллекцию (ТОЛЬКО ЭТО НОВОЕ)
				$forEvents = $request->boolean('for_events');
				
				if ($forEvents && !(auth()->user()?->isAdmin() || auth()->user()?->isOrganizer())) {
					return response()->json(['success' => false, 'error' => 'Нет прав для загрузки фото мероприятий'], 403);
				}			
				
				$collection = $forEvents ? 'event_photos' : 'photos';
				
				// 6. Сохраняем оригинал
				$media = $user->addMedia($originalFile)
                ->preservingOriginal()
                ->usingFileName($originalFile->getClientOriginalName())
                ->toMediaCollection($collection);
				
				// 7. Сохраняем thumb (как было, через file_put_contents)
				// Сделай:
				if ($forEvents) {
					$thumbPath = $media->getPath('event_thumb');
					} else {
					$thumbPath = $media->getPath('thumb');
				}
				
				if (!$thumbPath) {
					$media->delete();
					return response()->json([
                    'success' => false,
                    'error' => 'Не удалось получить путь к thumb'
					], 500);
				}
				
				if (!file_exists(dirname($thumbPath))) {
					mkdir(dirname($thumbPath), 0755, true);
				}
				
				file_put_contents($thumbPath, file_get_contents($croppedFile->getRealPath()));
				
				// 8. Устанавливаем аватар (только для обычных фото)
				$makeAvatar = $request->boolean('make_avatar');
				$hasAvatar = (bool) $user->avatar_media_id;
				
				if (!$forEvents && ($makeAvatar || !$hasAvatar)) {
					$user->update(['avatar_media_id' => $media->id]);
				}
				
				return response()->json([
                'success' => true,
                'message' => 'Фото добавлено',
                'media_id' => $media->id
				]);
				
				} catch (\Exception $e) {
				Log::error('Upload error: ' . $e->getMessage());
				return response()->json([
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
				], 500);
			}
		}
		
		public function setAvatar(Request $request, Media $media)
		{
			// Проверяем права: либо владелец, либо админ/организатор
			$isOwner = (int) $media->model_id === (int) $request->user()->id;
			$isAdmin = auth()->user()?->isAdmin();
			
			if (!$isOwner && !$isAdmin) {
				abort(403);
			}
			
			// Проверяем, что фото из коллекции photos (не event_photos)
			if ($media->collection_name !== 'photos') {
				return back()->with('error', 'Только фото из галереи можно сделать аватаром ❌');
			}
			
			$user = User::find($media->model_id);
			$user->update(['avatar_media_id' => $media->id]);
			
			return back()->with('status', 'Аватар обновлён ✅');
		}
		
		public function destroy(Request $request, Media $media)
		{
			// Проверяем права: либо владелец, либо админ/организатор
			$isOwner = (int) $media->model_id === (int) $request->user()->id;
			$isAdmin = auth()->user()?->isAdmin();
			
			if (!$isOwner && !$isAdmin) {
				abort(403);
			}
			
			$user = User::find($media->model_id);
			$deletedId = $media->id;
			$wasAvatar = ($user->avatar_media_id == $deletedId);
			
			$media->delete();
			
			if ($wasAvatar) {
				$fallback = $user->getMedia('photos')->sortByDesc('created_at')->first();
				$user->update(['avatar_media_id' => $fallback?->id]);
			}
			
			// Возвращаемся обратно на страницу пользователя
			$redirectUrl = $request->user()->isAdmin() 
			? route('user.photos') . '?user_id=' . $user->id
			: back()->getTargetUrl();
			
			return redirect($redirectUrl)->with('status', 'Фото удалено ✅');
		}
		
		public function destroyEventPhoto(Request $request, Media $media)
		{
			// Проверяем права: либо владелец, либо админ/организатор
			$isOwner = (int) $media->model_id === (int) $request->user()->id;
			$isAdmin = auth()->user()?->isAdmin() || auth()->user()?->isOrganizer();
			
			if (!$isOwner && !$isAdmin) {
				abort(403);
			}
			
			if ($media->collection_name !== 'event_photos') {
				return back()->with('error', 'Это не фото мероприятия ❌');
			}
			
			$user = User::find($media->model_id);
			$media->delete();
			
			$redirectUrl = $request->user()->isAdmin() 
			? route('user.photos') . '?user_id=' . $user->id
			: back()->getTargetUrl();
			
			return redirect($redirectUrl)->with('status', 'Фото мероприятия удалено ✅');
		}
		
		private function assertOwnedByUser(Media $media, User $user): void
		{
			abort_unless(
            $media->model_type === User::class && (int) $media->model_id === (int) $user->id,
            403
			);
		}
	}		