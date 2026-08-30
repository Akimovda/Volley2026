# BLE-трекинг пульса (Activity) — полная документация

Краткая версия — в CLAUDE.md, раздел "BLE-трекинг пульса (Activity)". Здесь — полная схема таблиц, конфиг, capabilities-паттерн, формулы и API.

## Таблицы
- `activity_sessions` — одна запись на тренировку: avg/max/min/hr, duration_sec, load_score, **calories_kcal** (decimal 7,2, null), time_in_zone (jsonb), samples_count, **jump_count** (int, default 0), **jump_avg_height_cm** / **jump_max_height_cm** (decimal 5,1, null), **tracked_capabilities** (jsonb, null)
- `activity_hr_samples` — сырые сэмплы (session_id, t_offset_sec, bpm); uq constraint → idempotent ingest
- **`activity_jump_events`** — прыжки (session_id FK cascade, **t_offset_ms** int — миллисекунды, НЕ секунды, в отличие от `activity_hr_samples.t_offset_sec`; height_cm decimal 5,1 null, type varchar null); `$timestamps=false`; unique(session_id, t_offset_ms) → idempotent ingest
- `athlete_devices` — зарегистрированные BLE-устройства пользователя; поле `protocol` определяет capabilities
- `athlete_profiles` — resting_hr, max_hr, weight_kg (нужен для калорий), **reach_classic_cm** / **reach_beach_cm** (smallint null); классика — в обуви, пляж — босиком
- **`user_consents`** — append-only согласия: user_id, type ('health_activity'), document_version, locale, accepted_at; index (user_id, type)

## Конфиг (config/activity.php)
- `recording_open` (env ACTIVITY_RECORDING_OPEN, по умолч. false) — запись открыта только для админов
- `consent_version` (env ACTIVITY_CONSENT_VERSION, по умолч. '2026-06-21')
- **`device_capabilities`** — маппинг protocol → capabilities:
  - `ble_hrp` → `['hr']` (стандартный BLE HR-пояс, прыжки не меряет)
  - `healthkit` / `polar_sdk` / `health_connect` → `['hr', 'jumps']`
- `default_capabilities` → `['hr']`
- **КРИТИЧНО**: перед добавлением UI прыжков — всегда проверять `tracked_capabilities`, не `protocol` напрямую

## Capabilities-паттерн
- `AthleteDevice::capabilities()` → `config('activity.device_capabilities')[$this->protocol] ?? default`
- `ActivitySessionService::start()` фиксирует `tracked_capabilities = $device->capabilities()` в момент старта сессии; если устройство не задано — `default_capabilities`
- Возможности сессии неизменны после старта (устройство можно переключить, но сессия уже зафиксирована)
- Фильтр jumps-сессий в PostgreSQL: `whereRaw("tracked_capabilities::jsonb @> '\"jumps\"'")`

## Коэффициент высоты прыжка (jump_height_coeff)
- Конфиг: `activity.jump_height_coeff` — map protocol → float|null; `activity.jump_height_coeff_default = 0.55`
  - `healthkit` = 0.533, `health_connect` = 0.533, `polar_sdk` = null (откалибровать позже)
- Колонка: `athlete_profiles.jump_height_coeff` DECIMAL(4,3) NULL — личный коэффициент атлета
- Сервис: `AthleteProfileService::effectiveJumpCoeff(User, ?AthleteDevice): float`
  - Приоритет: личный (`athlete_profiles.jump_height_coeff` не NULL) → конфиг по протоколу (не null) → дефолт 0.55
- **POST /api/activity/sessions** возвращает `jump_height_coeff` в ответе рядом с `session_id` — клиент использует его для конвертации акселерометра в высоту
- Новый протокол? — добавлять в `config/activity.php` в оба ключа (`device_capabilities` + `jump_height_coeff`)

## Прыжки (C)
- `ActivitySessionService::ingestJumps($session, $jumps)` — идемпотентный приём батча через `insertOrIgnore` по uq(session_id, t_offset_ms)
- `finalize()` агрегирует jump_count/avg/max из `activity_jump_events` за раз (один SQL)
- **Правило**: `finalize()` идемпотентен — если `status==='completed' && finalized_at!==null`, повторный вызов не трогает время/калории (иначе ретрай после сбоя доставки `/jumps` безусловно перезаписывал `ended_at`/`duration_sec`/`calories_kcal`, мог тихо понизить источник калорий `healthkit→keytel→null`), только `$session->save()` + `recomputeAggregates()`.
- **Клиентская цепочка (часы)**: `POST /samples` (fire-and-forget) → `POST /jumps` (гейт: ошибка → `completion(false)`, `/finalize` не вызывается) → `POST /finalize` только при 2xx от jumps. Оба фикса (клиент+сервер) нужны одновременно: гейт не спасает от повторного `/finalize` после потери ответа по сети, идемпотентность на сервере не спасает от преждевременного `/finalize` при живом jumps-запросе.
- **ГРАБЛИ для гейта "jumps 2xx" на клиенте**: HR-only устройства (`tracked_capabilities` без `jumps`, напр. `ble_hrp`) не имеют прыжков для отправки — если клиент в этом случае просто ПРОПУСКАЕТ вызов `/jumps` (а не шлёт пустой `jumps: []`), гейт "только после jumps 2xx" никогда не выполнится → `/finalize` не вызовется НИКОГДА для HR-only тренировок (дедлок, регресс). Сервер такой пустой запрос принимает нормально (`ActivityJumpController::store()` — `jumps` required+array, пустой массив валиден; `ingestJumps()` возвращает 0 через early return, `200 OK`) — поэтому гейт должен быть либо capability-aware (пропускать проверку, если устройство не поддерживает jumps), либо клиент должен всегда слать `/jumps` (даже с пустым массивом) для единообразия.
- **heightTrend($session)**: сравнивает `jump_avg_height_cm` с последними 5 завершёнными сессиями того же `user_id`+`direction`, у которых `tracked_capabilities @> 'jumps'`; HR-only сессии ИСКЛЮЧЕНЫ из тренда
  - Возвращает `['first' => true]` если нет предыдущих jumps-сессий
  - Или `['avg_prev' => float, 'delta' => float, 'label' => 'higher'|'lower']`
- Высоту показываем как ТРЕНД, не абсолютные сантиметры
- **Hitting reach** (≈ reach + jump_max): вычисляется на стороне JS из `config.reachClassicCm`/`reachBeachCm` + `data.jump_max_height_cm` из finalize-ответа

## Согласие (A)
- `User::hasHealthConsent()` — проверяет наличие строки в `user_consents` с type='health_activity' И текущей `consent_version`
- При бампе `consent_version` в .env все пользователи должны переподписать (true → false)
- `POST /api/activity/consent` → `ActivityConsentController::store()` — идемпотентно, пишет только если нет записи с текущей версией
- Блейд `record.blade.php`: если `!hasHealthConsent` — показывает блок с чекбоксом ПЕРЕД кнопкой подключения; по отметке → AJAX POST к API → скрывает блок
- JS: `connectSensor()` в начале проверяет `config.hasHealthConsent`; без согласия — показывает блок, не идёт дальше

## Калории (B) — формула Keytel 2005
- Сервис: `ActivityCalorieService::keytelKcalPerMin(hr, weightKg, age, gender)`
  - M: EE(кДж/мин) = -55.0969 + 0.6309×hr + 0.1988×weight + 0.2017×age
  - F: EE(кДж/мин) = -20.4022 + 0.4472×hr - 0.1263×weight + 0.074×age
  - kcal/min = EE/4.184; max(0, value)
- `finalize()`: каждый сэмпл = 1 сек → ккал += keytelKcalPerMin(bpm,...)/60; если нет weight/birth_date/gender → calories_kcal=null
- **gender**: 'm'/'f' (не 'male'/'female')
- Итоги: если null → ссылка «Укажите вес в настройках»

## Маршруты API (auth:sanctum,web)
- `POST /api/activity/consent` — принять согласие
- `POST /api/activity/devices` — зарегистрировать BLE-устройство
- `POST /api/activity/sessions` — начать сессию
- `POST /api/activity/sessions/{id}/samples` — батчевый приём сэмплов (idempotent)
- `POST /api/activity/sessions/{id}/jumps` — батчевый приём прыжков (idempotent)
- `POST /api/activity/sessions/{id}/finalize` — завершить; возвращает avg/max/min/load/calories/zones + tracked_capabilities, direction, jump_count/avg/max, jump_trend

## JS (resources/js/ble-activity.js)
- Capacitor `@capacitor-community/bluetooth-le` + `@capacitor-community/keep-awake`
- Flush сэмплов каждые 10 сек; при разрыве — reconnect до 10 попыток
- Если не в Capacitor (браузер) — скрывает управление, показывает alert
- **`renderJumpSummary(data)`** — capability-aware: проверяет `data.tracked_capabilities.includes('jumps')`
  - `true` → показывает `#ble-sum-jumps-block` (счётчик + тренд + hitting reach)
  - `false` → показывает `#ble-sum-jumps-not-tracked` («Этот датчик не отслеживает прыжки»); «0 прыжков» НИКОГДА не показывается для HR-only датчиков
- Цвет тренда: зелёный (#4caf50) если higher, красный (#f44336) если lower, серый при first
- `direction` берётся из `data.direction` (finalize-ответ), reach — из `config.reachBeachCm` или `config.reachClassicCm`
- `window.__activityConfig` расширен: `reachClassicCm`, `reachBeachCm`, `jumpI18n` (объект с ключами из `lang/*/activity.php`)

## sync_status — вычисляемый accessor, не колонка
- `ActivitySession::getSyncStatusAttribute()` (app/Models/ActivitySession.php) — НЕТ такой колонки в БД, чисто вычисляется из `status`+`finalized_at`(+`started_at`): `completed` без `finalized_at` → `'completed'`; `finalized_at` моложе `activity.settling_minutes` (default 5 мин) → `'settling'`; иначе `'completed'`; если `status!='completed'` → `'pending'`/`'stale'` от `activity.sync_stale_hours`. При ручном тестировании через `new ActivitySession([...])` — `finalized_at` обязательно выставлять явно (`->finalized_at = now()->subMinute()`), иначе accessor всегда вернёт `'completed'`, минуя settling/pending/stale ветки.

## Бейджи activity/index.blade.php + activity/show.blade.php — были сломаны фантомными классами (исправлено)
- `.badge` в style.css — это круглый индикатор-точка 1.5rem (`width:1.5rem;height:1.5rem;display:block`), используется на других страницах (profile/show, notification-channels) как ПУСТОЙ `<span>` без текста. Activity-страницы навесили НА ТОТ ЖЕ класс `.badge` текстовые бейджи с эмодзи+словами (`⏳ Данные ещё поступают`) — фиксированный 1.5rem-квадрат схлопывал текст в вертикальный перенос по словам, бейдж раздувался по высоте и наезжал на соседний контент.
- `.badge-sm`, `.badge-blue`, `.badge-orange` — фантомные классы, использовались ТОЛЬКО в этих двух blade-файлах, в CSS не существовали никогда. `.d-flex` — реальный класс (style.css/lib.css), но `justify-between`/`align-center` — тоже фантомы (правильные Bootstrap-имена — `justify-content-between`/`align-items-center`, но и те не подключены — Bootstrap не используется).
- Фикс: `.badge.badge-sm` (двойной класс, приоритет над одиночным `.badge`) переопределяет на `display:inline-flex;width:auto;height:auto;padding;border-radius;white-space:nowrap` — стиль в style.css рядом с исходным `.badge`. `badge-blue`/`badge-orange`/`badge-sync-info`/`badge-sync-danger` — цветовые модификаторы, вынесены из инлайн-style в классы. Для строки «заголовок слева + кнопка/бейджи справа» переиспользован существующий `.section-title-row` вместо `d-flex justify-between align-center`.
- Бейдж синхронизации (`pending`/`stale`/`settling`) перенесён на отдельную строку ПОД датой — длинный текст не должен делить строку с другим контентом. Короткие бейджи (направление, источник BLE) остались в исходном месте.

## load_score — decimal:2 cast возвращает СТРОКУ, "0.00" truthy в PHP
- `ActivitySession.load_score` — `protected $casts = ['load_score' => 'decimal:2']` → Eloquent отдаёт СТРОКУ вида `"0.00"`, не float/null. В PHP непустая строка `"0.00"` truthy (falsy — только `""` и точная строка `"0"`) → `{{ $session->load_score ? number_format(...) : '—' }}` показывал **«Нагрузка 0»** вместо прочерка при реальном нулевом значении (например когда весь пульс тренировки ниже нижней границы z1 — нет ни одной секунды в зоне, load считается от zone-time и выходит 0).
- Фикс везде (activity/index.blade.php ×2, activity/show.blade.php ×1): `(float) $session->load_score > 0 ? number_format(...) : '—'` — числовое сравнение вместо truthy-проверки строки. Общее правило: **любое `decimal:N`-поле в `@if`/тернарнике оборачивать в `(float)` перед проверкой**, не полагаться на truthiness.
- Аналогичный кейс — «Время по зонам» на show.blade.php: блок и раньше корректно скрывался при `$totalZoneSec == 0` (пульс не заходил ни в одну зону), но скрывался ПОЛНОСТЬЮ без объяснения — выглядело как потеря данных. Добавлен `@else`-блок с текстом `activity.zones_below_z1` (:bpm = нижняя граница z1 из `$zones['z1']['low']`, профильный Карвонен-расчёт).
