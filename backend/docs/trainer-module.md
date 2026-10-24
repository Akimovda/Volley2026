# Тренерский профиль

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Тренерский профиль внутри формы профиля (2026-10-01)
- Страница `/trainer/profile/edit` убрана: роут `trainer.profile.edit` — редирект на `/profile/complete?section=trainer#trainer`, `trainer.profile.update` и вью `trainer/profile_edit` удалены. `section=trainer` обязателен в ссылке: без него `ProfileCompletionController::show()` редиректит полностью заполненный профиль на `/user/profile`.
- Блок «Я тренер» — партиал `profile/_trainer_fields.blade.php` внутри основной формы `profile.extra.update` (поля `trainer[enabled|specialization|experience_years|bio]` + `trainer_present=1`). `ProfileExtraController` читает их ДО `$request->replace(allowlist)` и сохраняет на шаге 5 через `TrainerProfileService::applyFromProfile()`. Режим `organizer_other` блок игнорирует.
- Снятие галочки НЕ удаляет `trainer_profiles` — только `is_public=false` (оценки привязаны к user_id тренера и сохраняются; `User::isTrainer()` = exists()). Повторная галочка возвращает всё.
- «О себе» — Trix + эмодзи-панель (`assets/trix.js` + `assets/org.js` + `org.css`, подключены в `complete.blade.php`), на сервере `Purifier::clean('default')`; вывод — accessor `TrainerProfile::bio_html` (старый plain-text → `nl2br(e())`).
- **Отзывы/оценки привязаны к тренерам СОБЫТИЯ, не к профилю**: `TrainerRatingService::rate()` пускает только участника тура и только если тренер в `event_trainers` (или override occurrence) — см. `TrainerResolverService`. Для форматов training/training_game/camp/coach_student тренер в событии обязателен. Поэтому в форме создания/редактирования события (партиал `events/_partials/trainer_self.blade.php`) у организатора с активным тренерским профилем есть галочка «Тренер — я», она добавляет/убирает его в chips (при создании включена по умолчанию, если тренеры не заданы; в edit — отражает текущее состояние, API `window.__mgmtTrainerApi`).
- Приглашения школ (confirm/decline) и список школ тренера теперь в блоке «Тренер» на `/user/profile`.
