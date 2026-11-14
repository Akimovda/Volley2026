<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CourtBooking;
use App\Models\RentalClient;
use Illuminate\Http\Request;

/** «Клиенты» арендатора: гости, бронировавшие через виджет. Виден только владельцу площадки (админ — своим). */
class ClubClientsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->is_club_manager, 403);

        $q = trim((string) $request->query('q', ''));
        $clients = RentalClient::where('owner_id', $user->id)
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D+/', '', $q);
                $w->where(function ($x) use ($q, $digits) {
                    $x->where('name', 'ilike', '%' . $q . '%')->orWhere('email', 'ilike', '%' . $q . '%');
                    if ($digits !== '') {
                        $x->orWhere('phone', 'like', '%' . $digits . '%');
                    }
                });
            })
            ->withCount(['bookings as bookings_count' => fn ($b) => $b->whereIn('status', CourtBooking::ACTIVE_STATUSES)])
            ->withSum(['bookings as spent' => fn ($b) => $b->where('status', CourtBooking::STATUS_PAID)], 'price_total')
            ->withMax('bookings as last_at', 'starts_at')
            ->orderByDesc('last_at')->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('club.clients', ['clients' => $clients, 'q' => $q]);
    }

    public function update(Request $request, RentalClient $client)
    {
        abort_unless($request->user() && (int) $client->owner_id === (int) $request->user()->id, 403);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $client->update(['notes' => $data['notes'] ?? null]);

        return back()->with('success', __('club.clients_saved'));
    }

    /** Удаление клиента (по запросу субъекта ПДн): брони остаются, персональные данные в них обезличиваются. */
    public function destroy(Request $request, RentalClient $client)
    {
        abort_unless($request->user() && (int) $client->owner_id === (int) $request->user()->id, 403);
        CourtBooking::where('rental_client_id', $client->id)
            ->update(['guest_name' => __('club.clients_deleted'), 'guest_phone' => null, 'guest_email' => null, 'guest_comment' => null]);
        $client->delete();

        return back()->with('success', __('club.clients_deleted_ok'));
    }
}
