<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Типы уведомлений, которые отправляются кодом, но не имели строки в notification_templates (невидимы в админке). */
    private const TEMPLATES = [
        'new_event_in_city'                => 'Новое мероприятие в вашем городе',
        'weekly_digest'                    => 'Еженедельный дайджест (Premium)',
        'cash_payment_control_reminder'    => 'Контрольное напоминание о наличной оплате',
        'cash_payment_banned'              => 'Запрет наличной оплаты',
        'auto_booking_created'             => 'Авто-запись по абонементу: вы записаны',
        'auto_booking_failed'              => 'Авто-запись по абонементу: не удалось записать',
        'auto_booking_unconfirmed'         => 'Авто-запись по абонементу: не подтверждено, запись отменена',
        'premium_auto_booking_created'     => 'Premium авто-запись: вы записаны',
        'premium_auto_booking_failed'      => 'Premium авто-запись: не удалось записать',
        'premium_auto_booking_unconfirmed' => 'Premium авто-запись: не подтверждено, запись отменена',
        'team_join_request'                => 'Команда: заявка на вступление',
        'team_join_accepted'               => 'Команда: заявка принята',
        'team_join_declined'               => 'Команда: заявка отклонена',
        'team_member_left'                 => 'Команда: участник вышел',
        'team_disbanded'                   => 'Команда: расформирована',
        'team_captain_transferred'         => 'Команда: передано капитанство',
        'team_reserve_spot_offered'        => 'Команда: предложено место в резерве',
        'tournament_application_received'  => 'Турнир: заявка команды получена',
        'tournament_organizer_added'       => 'Турнир: организатор добавил вас в команду',
        'court_booking_requested'          => 'Бронь корта: заявка создана',
        'court_booking_confirmed'          => 'Бронь корта: подтверждена',
        'court_booking_paid'               => 'Бронь корта: оплачена',
        'court_booking_changed'            => 'Бронь корта: изменена',
        'court_booking_cancelled'          => 'Бронь корта: отменена',
        'court_booking_rejected'           => 'Бронь корта: отклонена',
        'court_booking_expired'            => 'Бронь корта: истёк срок оплаты',
        'court_booking_refunded'           => 'Бронь корта: возврат средств',
        'court_booking_reminder'           => 'Бронь корта: напоминание',
        'organizer_pro_activated'          => 'Организатор Pro: активирован',
        'organizer_pro_granted'            => 'Организатор Pro: выдан администратором',
        'organizer_pro_deactivated'        => 'Организатор Pro: отключён',
        'premium_payment_pending'          => 'Premium: оплата ожидает подтверждения',
        'premium_activated'                => 'Premium: активирован',
        'premium_deactivated'              => 'Premium: отключён администратором',
        'ad_event_payment_result'          => 'Рекламное мероприятие: результат оплаты',
        'organizer_request'                => 'Заявка на роль организатора',
        'personal_bot_revoked'             => 'Личный бот организатора отключён',
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')->whereIn('code', array_keys(self::TEMPLATES))->pluck('code')->all();

        $rows = [];
        foreach (self::TEMPLATES as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }
            // is_active=false + пустые тексты: содержание задаётся в коде, шаблон не применяется (только видимость в админке)
            $rows[] = [
                'code' => $code, 'channel' => null, 'name' => $name,
                'title_template' => null, 'body_template' => null,
                'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('notification_templates')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('code', array_keys(self::TEMPLATES))
            ->where('is_active', false)->whereNull('title_template')->whereNull('body_template')->delete();
    }
};
