<?php

return [
    // Боковое меню профиля (_menu.blade.php)
    'menu_public_profile_other' => 'Публичный профиль пользователя',
    'menu_edit_user'            => 'Редактировать пользователя',
    'menu_edit_user_photos'     => 'Редактировать фото пользователя',

    'menu_tab_player'    => 'Игрок',
    'menu_tab_organizer' => 'Организатор',

    'menu_public_profile' => 'Публичный профиль',
    'menu_my_profile'     => 'Мой профиль',
    'menu_edit_profile'   => 'Редактировать профиль',
    'menu_my_photos'      => 'Мои фотографии',
    'menu_notifications'  => 'Уведомления',
    'menu_my_subs'        => 'Мои абонементы',
    'menu_my_coupons'     => 'Мои купоны',
    'menu_my_events'      => 'Мои мероприятия',
    'menu_my_stats'       => 'Моя статистика',
    'menu_my_friends'     => 'Мои друзья',
    'menu_my_visitors'    => 'Мои гости',
    'menu_my_visitors_premium' => 'Мои гости 👑',
    'menu_logout'         => 'Выйти',

    'menu_org_dashboard'    => 'Панель организатора',
    'menu_org_create_event' => 'Создать мероприятие',
    'menu_org_subs'         => 'Абонементы (орг.)',
    'menu_org_sub_tpls'     => 'Шаблоны абонементов',
    'menu_org_coupon_tpls'  => 'Шаблоны купонов',
    'menu_org_staff'        => 'Мои помощники',
    'menu_org_staff_logs'   => '📋 Логи Staff',
    'menu_school_edit'      => 'Редактировать школу',
    'menu_school_show'      => 'Страница школы',
    'menu_school_create'    => 'Создать школу',
    'menu_delete_account'   => 'Удалить аккаунт',

    // === show.blade.php ===

    // Шапка / meta
    'show_title'         => 'Мой профиль',
    'show_description_prefix' => 'Мой профиль:',
    'show_user_n'        => 'Пользователь #:id',
    'show_t_description' => 'Здесь отображаются данные вашей анкеты.',

    // Дубль аккаунта
    'dupe_title'         => 'Возможный дубль аккаунта',
    'dupe_text_lead'     => 'Найден другой аккаунт с таким же номером телефона:',
    'dupe_text_action'   => 'Если это вы — обратитесь к администратору для объединения аккаунтов, или',
    'dupe_link_text'     => 'перейдите на страницу дублей',
    'dupe_text_admin'    => '(для администраторов).',
    'dupe_user_id_prefix' => 'ID #',

    // Персональные данные
    'sec_personal'      => 'Персональные данные',
    'pers_last_name'    => 'Фамилия:',
    'pers_first_name'   => 'Имя:',
    'pers_patronymic'   => 'Отчество:',
    'pers_phone'        => 'Телефон:',
    'pers_gender'       => 'Пол:',
    'pers_gender_m'     => 'Мужчина',
    'pers_gender_f'     => 'Женщина',
    'pers_height'       => 'Рост:',
    'pers_height_unit'  => 'см',
    'pers_city'         => 'Город:',
    'pers_birth'        => 'Дата рождения:',
    'pers_age'          => '(:age лет)',

    // Навыки
    'sec_skills'        => 'Навыки в волейболе',
    'skill_classic'     => 'Классический волейбол',
    'skill_beach'       => 'Пляжный волейбол',
    'skill_level'       => 'Уровень:',
    'skill_role'        => 'Амплуа игрока:',
    'skill_role_extra'  => 'Дополнительно:',
    'skill_zone'        => 'Зона игры:',
    'skill_universal'   => 'Универсал (2 и 4)',
    'skill_zone_main'   => 'Основная:',
    'skill_zone_extra'  => '; Доп.:',

    // Длинные названия позиций (используются в classic-блоке)
    'pos_long' => [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный блокирующий',
        'libero'   => 'Либеро',
    ],

    // Подсказка провайдеров
    'phint_only_one'    => 'Привязан только 1 способ входа',
    'phint_two_of_three' => 'Привязано 2 из 3 способов входа',
    'phint_lead'        => 'Привяжите все три провайдера (Telegram, VK, Яндекс) — это защитит аккаунт от потери доступа и поможет системе не создавать дубли.',
    'phint_link'        => 'Привязать →',

    // Привязка провайдеров
    'sec_providers'     => 'Привязка входов',
    'providers_lead'    => 'Привяжите дополнительные способы входа к текущему аккаунту.',
    'providers_current' => 'Текущий вход:',
    'providers_unknown' => 'Не определён',
    'providers_all_linked' => 'Все способы входа уже привязаны !',
    'providers_how'     => 'Как привязать:',
    'providers_step_1'  => 'Нажмите кнопку нужного провайдера ниже.',
    'providers_step_2'  => 'Подтвердите вход у провайдера.',
    'providers_step_3'  => 'После возврата на сайт провайдер привяжется к текущему аккаунту.',
    'providers_link_btn'   => 'Привязать',
    'providers_unlink_btn' => 'Отвязать',
    'providers_cant_unlink' => 'Нельзя отвязать',
    'providers_only_left'  => 'Отвязка последнего способа входа запрещена — сначала привяжите ещё один.',

    'unlink_confirm_vk'       => 'Отвязать VK от аккаунта?',
    'unlink_confirm_yandex'   => 'Отвязать Yandex от аккаунта?',
    'unlink_confirm_telegram' => 'Отвязать Telegram от аккаунта?',
    'unlink_confirm_apple'    => 'Отвязать Apple ID от аккаунта?',
    'unlink_confirm_google'   => 'Отвязать Google от аккаунта?',
    'unlink_confirm_yes'      => 'Да, отвязать',
    'unlink_confirm_no'       => 'Отмена',

    'tg_setup_error' => 'Ошибка настройки бота',
    'tg_button'      => 'Telegram',

    'max_in_dev'     => 'В разработке',

    // Face ID секция
    'sec_quick_login'      => 'Быстрый вход',
    'quick_login_lead'     => 'Face ID / Touch ID для входа в приложение.',
    'face_disable_btn'     => 'Отключить Face ID',
    'face_enable_btn'      => 'Включить Face ID',
    'face_unavailable'     => 'Face ID / Touch ID недоступен на этом устройстве.',
    'face_enabled_status'  => '✅ Face ID включён.',
    'face_not_setup'       => 'Face ID не настроен.',
    'face_status_error'    => 'Не удалось проверить статус.',
    'face_disable_error'   => 'Ошибка при отключении Face ID',
    'face_unavailable_alert' => 'Face ID недоступен.',
    'face_enable_error'    => 'Ошибка при включении Face ID',

    // Уведомления и рассылки
    'sec_notifications'    => 'Уведомления и рассылки',
    'notif_lead'           => 'Не пропустите ни одного важного события — подпишитесь на уведомления, и вы всегда будете в курсе новостей, анонсов мероприятий и своевременных напоминаний о начале.',
    'notif_tg_title'       => 'Уведомления в Telegram',
    'notif_max_title'      => 'Уведомления в MAX',
    'notif_vk_title'       => 'Уведомления в VK',
    'notif_tg_on'          => 'Личные уведомления в <b>Telegram</b> включены.',
    'notif_max_on'         => 'Личные уведомления в <b>MAX</b> включены.',
    'notif_vk_on'          => 'Личные уведомления во <b>VK</b> включены.',
    'notif_tg_offer'       => 'Хотите получать уведомления в <b>Telegram</b>?',
    'notif_max_offer'      => 'Хотите получать уведомления в <b>MAX</b>?',
    'notif_vk_offer'       => 'Хотите получать уведомления во <b>VK</b>?',
    'notif_tg_connect'     => 'Подключить Telegram',
    'notif_max_connect'    => 'Подключить MAX',
    'notif_vk_connect'     => 'Подключить VK',
    'notif_disable_btn'    => 'Отключить',

    'notif_tg_disconnect_title' => 'Отключить уведомления в Telegram?',
    'notif_max_disconnect_title' => 'Отключить MAX-уведомления?',
    'notif_vk_disconnect_title'  => 'Отключить уведомления во VK?',
    'notif_disconnect_yes'       => 'Да, отключить',

    'notif_tg_step_1' => 'Нажмите на ссылку ниже',
    'notif_tg_step_2' => 'Откройте личный чат с Telegram-ботом',
    'notif_tg_step_3' => 'Нажмите <b>Start</b>',
    'notif_tg_step_4' => 'После этого личные уведомления подключатся автоматически',

    'notif_max_step_1' => 'Нажмите на ссылку ниже',
    'notif_max_step_2' => 'Откройте личный чат с ботом MAX',
    'notif_max_step_3' => 'Нажмите <b>«Начать»</b>',
    'notif_max_step_4' => 'После этого личные уведомления подключатся автоматически',

    'notif_vk_step_1' => 'Нажмите на ссылку ниже',
    'notif_vk_step_2' => 'Откройте личный диалог с VK-ботом',
    'notif_vk_step_3' => 'Команда уже скопирована в буфер обмена',
    'notif_vk_step_4' => 'Просто вставьте её в чат и отправьте',
    'notif_vk_step_5' => 'После этого личные уведомления подключатся автоматически',
    'notif_vk_command_label' => 'Команда для бота (скопирована в буфер):',

    'notif_org_label'  => 'Уведомления о записях игроков',
    'notif_org_lead'   => 'При каждой записи или отмене записи на ваши мероприятия — личное сообщение от нашего бота через Telegram, VK или MAX.',
    'notif_org_on'     => 'Включены',
    'notif_org_off'    => 'Выключены',

    // Каналы уведомлений (sec)
    'sec_channels'        => 'Каналы уведомлений',
    'channels_list_label' => 'Список подключенных 📣:',
    'channels_default_n'  => 'Канал #:id',
    'channels_unverified' => '— не подтверждён',
    'channels_lead'       => 'Подключайте Telegram / VK / MAX каналы для анонсов мероприятий, открытия регистрации и обновления списков участников.',
    'channels_manage_btn' => 'Управление каналами уведомлений',

    // Платёжная система
    'sec_payment'         => '💳 Платёжная система',
    'pay_setup_title'     => 'Настройте приём оплаты',
    'pay_tab_events'      => '🏐 Для мероприятий',
    'pay_tab_premium'     => '👑 Premium и реклама',
    'pay_yoo_ok'          => '✅ Платежи настроены (ЮМани)',
    'pay_shop_id'         => 'Shop ID:',
    'pay_link_ok'         => '🔗 Настроены платежи по ссылке',
    'pay_tbank_label'     => 'Т-Банк:',
    'pay_sber_label'      => 'Сбер:',
    'pay_not_setup'       => '⚙️ Платежи не настроены',
    'pay_setup_btn'       => '⚙️ Настроить оплату',
    'pay_method_current'  => 'Текущий метод:',
    'pay_method_tbank'    => '🏦 Т-Банк (по ссылке)',
    'pay_method_sber'     => '💚 Сбер (по ссылке)',
    'pay_method_yoo'      => '🟡 ЮМани',
    'pay_premium_not_setup' => '⚙️ Платежи за Premium не настроены',
    'pay_premium_setup_btn' => '⚙️ Настроить оплату Premium',

    // Школы волейбола
    'sec_schools'         => 'Школы волейбола',
    'schools_admin_all'   => 'Все школы на платформе:',
    'schools_admin_create_btn' => '+ Создать для организатора',
    'schools_published'   => '✅ Опубликовано',
    'schools_hidden'      => '⏸ Скрыто',
    'schools_open_btn'    => 'Открыть',
    'schools_edit_btn'    => 'Редактировать',
    'schools_lead'        => 'Создайте публичную страницу вашей школы или волейбольного сообщества — там будут отображаться ваши мероприятия, описание и контакты.',
    'schools_create_btn'  => 'Создать страницу школы',

    // Premium
    'sec_premium'         => '👑 Premium подписка',
    'premium_active'      => '👑 Premium активен',
    'premium_until'       => 'До :date',
    'premium_plan_trial'  => 'Пробный период',
    'premium_plan_month'  => '1 месяц',
    'premium_plan_quarter' => '3 месяца',
    'premium_plan_year'   => 'Год',
    'premium_settings_btn' => '⚙️ Настройки',
    'premium_renew_btn'   => 'Продлить',
    'premium_offer_title' => 'Откройте возможности Premium',
    'premium_offer_li_1'  => '👑 Золотой аватар — выделяйтесь среди игроков',
    'premium_offer_li_2'  => '🥇 Приоритет в очереди резерва',
    'premium_offer_li_3'  => '👥 Друзья и гости профиля',
    'premium_offer_li_4'  => '📊 Детальная история игр и аналитика',
    'premium_offer_li_5'  => '🔔 Недельная сводка игр в вашем городе',
    'premium_subscribe_btn' => '👑 Подключить Premium',
    'premium_price_from'  => 'от 199₽ / месяц',

    // Приватность
    'sec_privacy'        => 'Приватность',
    'privacy_allow_text' => 'Разрешить другим пользователям писать вам в Telegram/VK со страницы профиля.',
    'privacy_lead'       => 'Кнопки «Написать» видны только авторизованным пользователям и только если вы включили этот переключатель.',
    'btn_save'           => 'Сохранить',

    // Запрос статуса организатора
    'sec_organizer_request'    => 'Хочу стать организатором мероприятий',
    'org_request_lead'         => 'Организатор может создавать мероприятия, управлять участниками и назначать помощников.',
    'org_request_pending'      => 'Ваша заявка уже отправлена и ожидает рассмотрения.',
    'org_request_comment_label' => 'Комментарий (необязательно)',
    'org_request_comment_ph'   => 'Например: регулярно организую игры и хочу делать это через Volley',
    'org_request_submit'       => 'Отправить заявку',

    // Удаление аккаунта
    'sec_delete'              => 'Удаление аккаунта',
    'delete_lead_html'        => 'После удаления все ваши данные будут безвозвратно удалены. У вас будет <strong id="grace-period-display">:n</strong> сек чтобы передумать и отменить действие.',
    'delete_btn'              => 'Удалить аккаунт',
    'delete_cancel_btn_html'  => 'Отменить удаление (<span id="countdown-seconds">0</span> сек)',

    // Удаление аккаунта — JS swal
    'delete_swal_q_title'     => 'Вы уверены?',
    'delete_swal_q_text'      => 'После удаления все ваши данные будут безвозвратно удалены. У вас будет :n секунд чтобы передумать.',
    'delete_swal_cancel'      => 'Отмена',
    'delete_swal_confirm_q'   => 'Да, хочу удалить',
    'delete_swal_w_title'     => 'Последнее предупреждение',
    'delete_swal_w_text'      => 'Введите слово УДАЛИТЬ для подтверждения',
    'delete_confirm_word'     => 'УДАЛИТЬ',
    'delete_swal_confirm_btn' => 'Подтвердить',
    'delete_swal_wrong_title' => 'Неверно',
    'delete_swal_wrong_text'  => 'Нужно ввести слово УДАЛИТЬ',
    'delete_swal_cancelled'   => 'Удаление отменено',
    'delete_swal_done_title'  => 'Аккаунт удалён',
    'delete_swal_done_text'   => 'Ваш аккаунт был успешно удалён. Все данные будут анонимизированы.',
    'delete_swal_err_title'   => 'Ошибка',
    'delete_swal_err_text'    => 'Не удалось удалить аккаунт',

    // Bind buttons (JS alerts)
    'bind_invalid_response'   => 'Сервер вернул некорректный ответ',
    'bind_link_error'         => 'Не удалось создать ссылку',
    'bind_request_error'      => 'Ошибка запроса при создании ссылки',

    // === complete.blade.php ===

    // Шапка
    'cp_title_admin_other'    => 'Редактирование профиля пользователя #:id',
    'cp_title_organizer'      => 'Настройка уровней игрока #:id',
    'cp_title_self'           => 'Редактирование данных вашего профиля',
    'cp_desc_admin_suffix'    => '— режим администратора',
    'cp_desc_organizer_suffix' => '— режим организатора (только уровни/дата рождения)',
    'cp_breadcrumb_self'      => 'Ваш профиль',
    'cp_breadcrumb_user_n'    => 'Пользователь #:id',
    'cp_breadcrumb_edit'      => 'Редактирование данных',
    'cp_h1'                   => 'Редактирование профиля',
    'cp_t_description'        => 'Заполните ключевые поля — после первого сохранения часть данных сможет менять только администратор.',

    // Подсказки
    'cp_lock_hint' => 'Поле уже заполнено. Изменить может только администратор.',

    // Welcome баннеры
    'cp_welcome_first_title' => 'Добро пожаловать на VolleyPlay.Club! 🏐',
    'cp_welcome_first_lead'  => 'Заполните анкету — это займёт меньше минуты.<br>После сохранения персональные данные нельзя будет изменить самостоятельно.',
    'cp_welcome_first_hint'  => 'Имя и фамилия подтянулись из вашего аккаунта — проверьте и при необходимости исправьте.',
    'cp_welcome_session_title' => 'Добро пожаловать на VolleyPlay.Club!',
    'cp_welcome_session_lead'  => 'Надеемся, Вам понравится наш сервис.<br>Для Вашего удобства заполните данные профиля — это займёт меньше минуты.',
    'cp_welcome_session_motto' => 'Удачных игр и тренировок! 💪',

    // Errors блок
    'cp_errors_title'        => 'Проверьте поля',
    'cp_required_title'      => 'Перед записью заполните:',
    'cp_required_full_name'  => 'Фамилия и имя',
    'cp_required_patronymic' => 'Отчество',
    'cp_required_phone'      => 'Телефон',
    'cp_required_city'       => 'Город',
    'cp_required_birth'      => 'Дата рождения',
    'cp_required_gender'     => 'Пол',
    'cp_required_height'     => 'Рост',
    'cp_required_classic'    => 'Уровень (классика)',
    'cp_required_beach'      => 'Уровень (пляж)',
    'cp_required_event_hint' => 'После сохранения профиля мы попробуем автоматически записать вас на мероприятие.',

    // Organizer view info
    'cp_organizer_view_info' => 'Вы редактируете профиль пользователя как организатор: доступны только дата рождения, амплуа/зона в пляжке и классике и уровни игры.',

    // Персональные данные
    'cp_sec_personal'  => 'Персональные данные',
    'cp_lbl_last'      => 'Фамилия',
    'cp_lbl_first'     => 'Имя',
    'cp_lbl_patronym'  => 'Отчество',
    'cp_lbl_phone'     => 'Телефон',
    'cp_lbl_birth'     => 'Дата рождения',
    'cp_lbl_city'      => 'Город',
    'cp_lbl_gender'    => 'Пол',
    'cp_lbl_height'    => 'Рост (см)',
    'cp_visible_all'   => 'Видно всем пользователям',
    'cp_visible_org'   => 'Видно только организаторам',
    'cp_visible_age_fmt' => 'Видно всем пользователям в формате: "30 лет"',

    'cp_hint_cyr_name'    => 'Кириллица, ≥2 символов, с заглавной',
    'cp_hint_height_range' => 'Допустимый диапазон: 40–230 см.',

    // Date selects
    'cp_birth_day_ph'   => 'День',
    'cp_birth_month_ph' => 'Месяц',
    'cp_birth_year_ph'  => 'Год',
    'cp_months' => [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ],

    // Age hide
    'cp_hide_age' => 'Скрыть мой возраст от других пользователей',

    // City
    'cp_city_search_ph'  => 'Начните вводить город…',
    'cp_city_search_hint' => 'Введите минимум 2 символа для поиска.',

    // Gender select
    'cp_gender_none'   => '— не указан —',
    'cp_gender_male'   => 'Мужчина',
    'cp_gender_female' => 'Женщина',

    // Skills
    'cp_sec_classic' => 'Классический волейбол',
    'cp_sec_beach'   => 'Пляжный волейбол',
    'cp_lvl_label'   => 'Уровень',
    'cp_select_pick' => '— выберите —',
    'cp_lvl_link'    => 'Подробная информация об уровнях игроков',

    'cp_age_msg_no_birth' => 'Сначала укажите дату рождения',
    'cp_age_msg_under_18' => 'Доступны только уровни 1, 2, 4',
    'cp_lvl_yours'        => 'Ваш уровень:',

    // Roles
    'cp_role_primary'   => 'Основное амплуа',
    'cp_role_extra'     => 'Дополнительное амплуа',
    'cp_role_primary_tag' => '(основное)',

    // Beach mode
    'cp_beach_mode_q'   => 'В какой зоне вы играете: 2, 4 или вы универсал?',
    'cp_beach_zone_2'   => 'Зона 2',
    'cp_beach_zone_4'   => 'Зона 4',
    'cp_beach_universal' => 'Универсал',
    'cp_beach_universal_hint' => 'Если выбран "Универсал", отметим зоны 2 и 4 и поставим пометку "универсальный игрок".',

    // Save
    'cp_btn_save' => 'Сохранить',

    // Profile prompt swal
    'cp_swal_prompt_title' => 'Приветствую! 👋',
    'cp_swal_prompt_text'  => 'Заполните обязательные поля: Ваше ФИО, номер сотового и город проживания!',
    'cp_swal_prompt_btn'   => 'Хорошо',

    // === Каталог игроков (users/index.blade.php) ===
    'idx_title_page'        => 'Игроки — Страница :n',
    'idx_desc_role'         => 'Игроки с ролью :role',
    'idx_desc_all'          => 'Все игроки платформы',
    'idx_breadcrumb'        => 'Игроки',
    'idx_h1'                => 'Игроки платформы',

    'idx_count_people'      => 'человек|человека|человек',
    'idx_count_found'       => 'найден|найдено|найдено',
    'idx_t_found_prefix'    => '',  // используется ucfirst($foundWord)
    'idx_t_registered'      => 'Зарегистрировано',

    'idx_btn_filter'        => 'Фильтр',
    'idx_label_name'        => 'Фамилия / имя',
    'idx_ph_name'           => 'Акимов Дмитрий',
    'idx_label_city'        => 'Город',
    'idx_any'               => '— любой —',
    'idx_label_gender'      => 'Пол',
    'idx_gender_m'          => 'Мужчина',
    'idx_gender_f'          => 'Женщина',
    'idx_label_classic_lvl' => 'Уровень (классика)',
    'idx_label_beach_lvl'   => 'Уровень (пляж)',
    'idx_label_age'         => 'Возраст',
    'idx_ph_age_min'        => 'от, напр. 18',
    'idx_ph_age_max'        => 'до, напр. 45',
    'idx_btn_search'        => 'Искать',
    'idx_btn_reset'         => 'Сбросить',
    'idx_empty_filtered'    => 'Ничего не найдено. Попробуй сбросить фильтры или изменить условия поиска.',

    'idx_search_no_results' => 'Ничего не найдено',
    'idx_search_searching'  => 'Поиск…',
    'idx_search_error'      => 'Ошибка загрузки',

    // === Карточка игрока (users/_card.blade.php) ===
    'card_user_n'          => 'Пользователь',
    'card_user_n_full'     => 'Пользователь #:id',
    'card_age_years'       => ':n лет',
    'card_height_cm'       => ':n см',
    'card_premium'         => 'Premium',
    'card_lvl_classic'     => 'Классика',
    'card_lvl_beach'       => 'Пляжка',

    // === Публичный профиль (users/show.blade.php) ===
    'pub_title_suffix'     => '— профиль игрока',
    'pub_title_fallback'   => 'Игрок',
    'pub_description'      => 'Профиль игрока :name на VolleyPlay.Club — статистика, позиции, турниры',
    'pub_not_found'        => 'Игрок не найден.',
    'pub_back_to_list'     => '← К списку игроков',
    'pub_to_events'        => 'К мероприятиям',

    'pub_contact_title'    => 'Связаться',
    'pub_contact_self'     => 'Это ваш профиль. Разрешение «могут ли со мной связаться» настраивается в',
    'pub_contact_self_link' => 'Аккаунт',
    'pub_contact_blocked'  => 'Пользователь запретил связываться с ним.',
    'pub_contact_no_links' => 'У пользователя не указаны публичные контакты (Telegram/VK).',
    'pub_contact_tg_no_username' => 'Telegram привязан, но нет username — ссылку на чат показать нельзя.',
    'pub_contact_login_required' => 'Чтобы написать пользователю, нужно войти в аккаунт.',

    'pub_personal_title'   => 'Персональные данные',
    'pub_field_lastname'   => 'Фамилия',
    'pub_field_firstname'  => 'Имя',
    'pub_field_patronymic' => 'Отчество',
    'pub_field_phone'      => 'Телефон',
    'pub_field_gender'     => 'Пол',
    'pub_field_city'       => 'Город',
    'pub_field_height'     => 'Рост',
    'pub_field_birthdate'  => 'Дата рождения',
    'pub_dash'             => '—',

    'pub_skills_title'     => 'Навыки в волейболе',
    'pub_skills_classic'   => 'Классический волейбол',
    'pub_skills_beach'     => 'Пляжный волейбол',
    'pub_skills_level'     => 'Уровень:',
    'pub_skills_role'      => 'Амплуа:',
    'pub_skills_zone'      => 'Зона:',
    'pub_skills_primary'   => 'Основное:',
    'pub_skills_primary_zone' => 'Основная:',
    'pub_skills_extra'     => 'Доп.:',
    'pub_beach_universal'  => 'Универсал (2 и 4)',

    'positions' => [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный блокирующий',
        'libero'   => 'Либеро',
    ],

    // === Фотографии пользователя (user/photos.blade.php) ===
    'photos_title'             => 'Мои фотографии',
    'photos_breadcrumb_profile' => 'Профиль',
    'photos_breadcrumb_self'   => 'Мои фотографии',
    'photos_t_description'     => 'Управление личными фотографиями',

    'photos_upload_title'      => 'Загрузить фото',
    'photos_upload_select'     => 'Выбрать файлы',
    'photos_upload_btn'        => 'Загрузить',
    'photos_upload_hint_size'  => 'Максимум :n МБ за файл. Поддерживаются JPG, PNG, WebP, HEIC.',
    'photos_upload_hint_count' => 'Можно выбрать несколько файлов сразу.',
    'photos_upload_no_files'   => 'Выберите файлы для загрузки',

    'photos_for_events_title'  => 'Использовать для мероприятий',
    'photos_for_events_hint'   => 'Отметьте фото, которые можно использовать как обложку для мероприятий.',

    'photos_section_my'        => 'Все мои фото',
    'photos_section_event'     => 'Фото для мероприятий',
    'photos_empty'             => 'Фото пока нет. Загрузите первое выше 🙂',
    'photos_empty_event'       => 'Нет фото, отмеченных для мероприятий.',

    'photos_btn_make_main'     => 'Сделать главным',
    'photos_btn_main'          => 'Главное',
    'photos_btn_for_event_on'  => 'В мероприятиях',
    'photos_btn_for_event_off' => 'Не в мероприятиях',

    'photos_delete_title'      => 'Удалить фото?',
    'photos_delete_text'       => 'Это действие нельзя отменить.',
    'photos_btn_delete'        => 'Удалить',
    'photos_btn_cancel'        => 'Отмена',
    'photos_btn_delete_yes'    => 'Да, удалить',

    'photos_uploading'         => 'Загрузка…',
    'photos_upload_success'    => 'Фото загружены',
    'photos_upload_error'      => 'Ошибка загрузки',

    // photos page (full)
    'photos_h1_self'        => 'Мои фотографии',
    'photos_h1_other'       => 'Редактирование фотографий профиля',
    'photos_h1_other_short' => 'Редактирование фотографий',
    'photos_user_n'         => 'Пользователь #:id',
    'photos_t_desc'         => 'Загружайте и управляйте своими фотографиями',
    'photos_breadcrumb_my_profile' => 'Мой профиль',

    'photos_filepond_idle'   => 'Перетащи фото или <span class="btn mt-1 mb-1">выбери файл</span> <span>Допустимые форматы: <b class="d-inline-block">JPEG, PNG, WEBP, AVIF</b></span>',
    'photos_filepond_remove' => 'Удалить',

    'photos_err_format_title' => 'Неподдерживаемый формат',
    'photos_err_format_text'  => "Можно загружать только изображения\n(JPEG, PNG, WEBP, AVIF)",
    'photos_err_size_title'   => 'Файл слишком большой',
    'photos_err_size_text'    => 'Максимальный размер: 15 МБ',
    'photos_err_understand'   => 'Понятно',
    'photos_err_title'        => 'Ошибка',
    'photos_err_upload'       => 'Ошибка загрузки',
    'photos_err_network'      => 'Проблема с соединением',

    'photos_crop_title'      => 'Выберите область',
    'photos_crop_save'       => 'Загрузить',
    'photos_crop_cancel'     => 'Отмена',

    'photos_added_success'   => 'Фото добавлено ✅',
    'photos_errors_title'    => 'Ошибки:',

    'photos_upload_h2'       => 'Загрузить фото',
    'photos_radio_gallery'   => 'В галерею',
    'photos_radio_avatar'    => 'Сделать аватаром',
    'photos_radio_event'     => 'Фото для мероприятий',
    'photos_radio_school_logo_disabled' => 'Логотип школы уже загружен, сначала удали старый',
    'photos_radio_school_logo' => 'Логотип школы',
    'photos_radio_school_cover' => 'Фотографии школы',

    'photos_gallery_h2'      => 'Галерея',
    'photos_total_prefix'    => 'Всего:',
    'photos_total_suffix'    => 'фото',
    'photos_empty_first'     => 'Фотографий нет, загрузите первое — и оно станет аватаром автоматически.',
    'photos_avatar_label'    => 'Аватар',
    'photos_make_avatar'     => 'Сделать фото<br>аватаром',
    'photos_confirm_delete'  => 'Удалить фото?',
    'photos_btn_delete_yes_short' => 'Да, удалить',

    'photos_event_h2'        => 'Фото для мероприятий',
    'photos_event_empty'     => 'Нет фото для мероприятий. Загрузите фото с выбором «Фото для мероприятий».',

    'photos_school_logo_h2'  => 'Логотип школы',
    'photos_school_logo_empty' => 'Нет логотипов. Загрузите фото с выбором «Логотип школы».',
    'photos_school_logo_replace' => 'Логотип уже загружен. Чтобы заменить — удалите текущий и загрузите новый.',
    'photos_confirm_delete_logo' => 'Удалить логотип?',
    'photos_delete_logo_title' => 'Удалить логотип?',

    'photos_school_cover_h2' => 'Фотографии школы',
    'photos_school_cover_empty' => 'Нет фото. Загрузите фото с выбором «Фотографии школы».',
    'photos_make_main_cover' => 'Сделать фото<br>основным',
    'photos_main_cover_label' => 'Основное фото',

    // === notification-channels.blade.php ===
    'nch_title'            => 'Каналы уведомлений',
    'nch_h2'               => 'Подключённые каналы для анонсов',
    'nch_t_description'    => 'Telegram, VK и MAX каналы для рассылки анонсов мероприятий.',
    'nch_breadcrumb'       => 'Профиль',
    'nch_breadcrumb_self'  => 'Каналы уведомлений',
    'nch_help_title'       => '❓ Что такое каналы уведомлений?',
    'nch_help_lead'        => 'Это ваш чат или канал в <strong>Telegram</strong> / <strong>MAX</strong>, куда наш бот автоматически отправляет анонсы ваших мероприятий. Для <strong>ВКонтакте</strong> анонсы публикуются прямо на стене вашего сообщества — через ключ доступа (см. блок ниже).',
    'nch_howto_h3'         => '📋 Telegram и MAX — как подключить',
    'nch_step1_title'      => 'Создайте ссылку привязки',
    'nch_step1_text'       => 'В блоке <strong>«Подключить канал»</strong> выберите платформу (Telegram или MAX), придумайте название — и нажмите кнопку. Сервис сгенерирует уникальную ссылку-приглашение.',
    'nch_step2_title'      => 'Добавьте бота в чат',
    'nch_step2_text'       => 'Нажмите кнопку «Открыть» — откроется мессенджер. Выберите свою группу или канал и добавьте бота <strong>с правами администратора</strong>.',
    'nch_step3_title'      => 'Канал подтверждён',
    'nch_step3_text'       => 'Бот автоматически отметит канал как подтверждённый. Обновите страницу — увидите его в списке с бейджем <span class="badge badge-green">подтверждён</span>.',

    'nch_platforms_h3'     => '🧭 Инструкции по платформам',
    'nch_advice'           => '<strong>💡 Совет:</strong> можно подключить <strong>несколько каналов</strong> на разных платформах — анонсы дублируются во все подтверждённые каналы одновременно. Ссылка привязки действительна <strong>30 минут</strong>.',

    'nch_link_created'     => '✅ Ссылка для привязки :platform создана',
    'nch_next_steps'       => '📋 Что делать дальше:',
    'nch_btn_open'         => 'Открыть',
    'nch_bot_command'      => 'Команда для отправки боту:',

    'nch_section_connect'  => 'Подключить канал',
    'nch_section_existing' => 'Подключённые каналы',
    'nch_no_channels'      => 'Каналов пока нет.',
    'nch_btn_create_link'  => 'Создать ссылку привязки',
    'nch_label_platform'   => 'Платформа',
    'nch_label_name'       => 'Название',
    'nch_label_chat_id'    => 'ID чата',
    'nch_status_verified'  => 'подтверждён',
    'nch_status_pending'   => 'ожидает',
    'nch_btn_test'         => 'Тест',
    'nch_btn_remove'       => 'Удалить',
    'nch_confirm_remove'   => 'Удалить канал?',

    // === payment/wallet ===
    'pay_wallet_title'     => 'Мой кошелёк',
    'pay_wallet_t_description' => 'Виртуальные средства от возвратов за мероприятия',
    'pay_wallet_balance'   => 'Баланс:',
    'pay_wallet_no_tx'     => 'Транзакций пока нет.',
    'pay_settings_t_description' => 'Настройте способы приёма оплаты за ваши мероприятия',
    'pay_tx_t_description' => 'История платежей ваших мероприятий',
    'check_fields'         => 'Проверьте поля',
    'col_event'            => 'Мероприятие',

    // === payment/settings ===
    'pay_settings_title'   => 'Настройки оплаты',
    'pay_settings_h2'      => 'Настройки приёма платежей',

    // === payment/transactions ===
    'pay_tx_title'         => 'Транзакции',
    'pay_tx_h2'            => 'История транзакций',
    'pay_tx_empty'         => 'Транзакций пока нет.',

    // === dashboard/player and dashboard/org ===
    'dash_player_title'    => 'Моя статистика',
    'dash_player_t_description' => 'Ваша активность на площадке',
    'dash_player_user_n'   => 'Пользователь #:id',
    'dash_player_breadcrumb_my' => 'Мой профиль',
    'dash_player_breadcrumb_self' => 'Моя статистика',

    'dash_player_h2_activity'  => 'Активность',
    'dash_player_total_games'  => 'Всего игр',
    'dash_player_this_month'   => 'В этом месяце',
    'dash_player_cancellations' => 'Отмен',
    'dash_player_streak'       => 'Серия недель',

    'dash_player_h2_rating'    => 'Рейтинг и оценки',
    'dash_player_lvl_votes'    => 'Оценок уровня',
    'dash_player_lvl_avg'      => 'Средний уровень',
    'dash_player_likes'        => 'Нравится',
    'dash_player_likes_sub'    => 'c вами играть',
    'dash_player_top_act'      => 'Топ активности',
    'dash_player_top_act_sub'  => 'игроков',

    'dash_player_h2_views'     => 'Просмотры профиля',
    'dash_player_views_all'    => 'За все время',
    'dash_player_views_30d'    => 'За последние 30 дней',
    'dash_player_h2_monthly'   => 'Активность по месяцам',
    'dash_player_h2_positions' => 'Позиции',
    'dash_player_h2_locations' => 'Площадки',
    'dash_player_no_data'      => 'Нет данных',

    'dash_org_title'       => 'Панель организатора',
    'dash_org_t_description' => 'Аналитика ваших мероприятий',
    'dash_org_breadcrumb_self' => 'Панель организатора',
    'dash_org_btn_my_events' => '📋 Мои мероприятия',
    'dash_org_btn_pay_settings' => '💳 Настройки оплаты',
    'dash_org_btn_transactions' => '💰 Транзакции',
    'dash_org_h2_summary'  => '📊 Сводка',
    'dash_org_total_events' => 'Всего мероприятий',
    'dash_org_active_events' => 'Активных',
    'dash_org_recurring'   => 'Регулярных',
    'dash_org_one_time'    => 'Разовых',
    'dash_org_h2_players'  => '👥 Игроки',
    'dash_org_unique_players' => 'Уникальных игроков',
    'dash_org_total_regs'  => 'Всего записей',
    'dash_org_new_30d'     => 'Новых за 30 дней',
    'dash_org_pageviews_30d' => 'Просмотров страниц (30д)',
    'dash_org_profile_views' => 'профиля:',
    'dash_org_h2_dynamics' => '📈 Динамика записей (12 месяцев)',
    'dash_org_h2_load'     => '🏐 Загрузка мероприятий (последние 3 месяца)',
    'dash_org_col_event'   => 'Мероприятие',
    'dash_org_col_repeats' => 'Повторов',
    'dash_org_col_total_regs' => 'Всего записей',
    'dash_org_col_avg_load' => 'Средняя загрузка',

    'dash_orgt_title'      => 'Аналитика турниров',
    'dash_orgt_breadcrumb_dash' => 'Панель организатора',
    'dash_orgt_breadcrumb_self' => 'Аналитика турниров',
    'dash_orgt_tournaments' => 'Турниров',
    'dash_orgt_matches'    => 'Матчей сыграно',
    'dash_orgt_unique_players' => 'Уникальных игроков',
    'dash_orgt_teams'      => 'Команд',
    'dash_orgt_avg_fill'   => 'Средняя заполняемость',
    'dash_orgt_avg_fill_sub' => '% команд от максимума',

    // === premium ===
    'premium_title'        => 'Premium подписка — Volley',
    'premium_h1'           => 'Premium подписка 👑',
    'premium_h2'           => 'Преимущества премиум',
    'premium_settings_title' => 'Настройки Premium — Volley',
    'premium_settings_h1'  => 'Настройки Premium 👑',
    'premium_settings_t_description' => 'Управляйте уведомлениями и фильтрами',

    // === volleyball_school ===
    'school_idx_title'     => 'Школы волейбола',
    'school_idx_description' => 'Школы и сообщества волейбола — тренировки, обучение, команды',
    'school_idx_t_description' => 'Школы, клубы и волейбольные сообщества',
    'school_create_title'  => 'Создать страницу школы',
    'school_create_t_description' => 'Расскажите о вашей школе или волейбольном сообществе',
    'school_edit_title'    => 'Редактировать страницу школы',
    'school_breadcrumb'    => 'Школы',

    // === profile/widget ===
    'widget_title'         => 'Виджет на сайт',
    'widget_h1'            => '🌐 Виджет на сайт',
    'widget_h2'            => 'Организатор Pro',
    'widget_t_description' => 'Встройте список ваших мероприятий на любой внешний сайт.',
    'widget_breadcrumb'    => 'Виджет',
    'widget_pro_section_h2' => 'Доступно в Организатор Pro',

    // === players/rating ===
    'rating_title'         => 'Рейтинг игроков',
    'rating_label_dir'     => 'Направление',
    'rating_dir_classic'   => 'Классический',
    'rating_dir_beach'     => 'Пляжный',
    'rating_label_season'  => 'Сезон',
    'rating_career_all'    => 'Карьерный (все)',
    'rating_label_sort'    => 'Сортировка',
    'rating_sort_matches'  => 'Матчей',
    'rating_btn_apply'     => 'Обновить',
    'rating_no_data'       => 'Нет данных для отображения. Рейтинг формируется после завершения турниров.',
    'rating_col_player'    => 'Игрок',
    'rating_col_league'    => 'Лига',
    'rating_col_rounds'    => 'Туров',
    'rating_col_matches'   => 'Матчей',
    'rating_col_wins'      => 'Побед',
    'rating_col_sets'      => 'Сеты',
    'rating_col_pts_diff'  => 'Очки ±',
    'rating_col_tournaments' => 'Турниров',
    'rating_player_n'      => 'Игрок #:id',

    // === teams/stats ===
    'team_stats_title'     => 'Статистика',
    'team_stats_matches'   => 'Матчей',
    'team_stats_wins'      => 'Побед',
    'team_stats_losses'    => 'Поражений',
    'team_stats_sets'      => 'Сеты:',
    'team_stats_points'    => 'Очки:',
    'team_stats_diff'      => 'Разница:',
    'team_stats_lineup'    => 'Состав',
    'team_stats_player_stats' => 'Статистика игроков',
    'team_stats_match_history' => 'История матчей',
    'team_stats_positions' => 'Позиции в турнирах',
    'team_stats_winrate'   => 'WinRate',
    'team_stats_col_stage' => 'Стадия',
    'team_stats_col_opp'   => 'Соперник',
    'team_stats_col_score' => 'Счёт',
    'team_stats_col_details' => 'Подробно',
    'team_stats_col_result' => 'Результат',
    'team_stats_col_group' => 'Группа',
    'team_stats_col_pos'   => 'Место',
    'team_stats_player_n'  => 'Игрок #:id',
    'team_stats_won'       => 'Победа',
    'team_stats_lost'      => 'Поражение',
    'team_stats_tech'      => 'Техн.',
    'team_stats_captain_short' => 'К',
    'team_stats_btn_csv'   => '📥 Скачать результаты турнира (CSV)',

    // === friends ===
    'friends_title'        => 'Мои друзья',
    'friends_empty'        => 'Друзей пока нет.',

    // === visitors ===
    'visitors_title'       => 'Мои гости',
    'visitors_empty'       => 'Гостей пока нет.',

    // === complete ===
    'complete_title'       => 'Завершите профиль',
];
