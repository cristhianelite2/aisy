<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\Ai\AiMemoryService;
use App\Services\Integrations\GoogleMapsKitService;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Odoo\OdooBusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessLandingController extends Controller
{
    public function show(
        string $slug,
        AiMemoryService $memory,
        GoogleMapsKitService $gmaps,
        WhatsAppGatewayClient $whatsapp
    ): View {
        $business = Business::where('slug', $slug)->firstOrFail();
        $catalog = $business->catalogItems()->where('active', true)->get();
        $promotions = $business->operations()->where('operation_type', 'promotion')->latest()->limit(3)->get();
        $gmapsKit = $business->gmaps_kit ?: $gmaps->generateKit($business);

        $memory->recordPageEvent($business, 'landing_view', [
            'branch' => $business->branch,
            'user_agent' => request()->userAgent(),
        ]);

        $waOwnerLink = $whatsapp->buildClickToChatLink(
            $business->owner_phone,
            "Hola {$business->name}, vengo de su página en Aisy."
        );

        return view('landing.show', compact('business', 'catalog', 'promotions', 'gmapsKit', 'waOwnerLink'));
    }

    public function trackEvent(Request $request, string $slug, AiMemoryService $memory): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $event = $memory->recordPageEvent(
            $business,
            (string) $request->input('event_type', 'interaction'),
            (array) $request->input('metadata', []),
            $request->session()->getId()
        );

        return response()->json(['ok' => true, 'event_id' => $event->id]);
    }

    public function submitAction(
        Request $request,
        string $slug,
        OdooBusinessService $odoo,
        AiMemoryService $memory
    ): RedirectResponse {
        $business = Business::where('slug', $slug)->firstOrFail();

        $customerName = (string) $request->input('customer_name', 'Cliente Web');
        $customerPhone = (string) $request->input('customer_phone', '');

        if ($business->branch === 'estetica') {
            $op = $odoo->scheduleAppointment($business, [
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'service_name' => (string) $request->input('service_name', 'Cita'),
                'scheduled_at' => $request->input('scheduled_at') ?: now()->addDay()->setTime(12, 0)->toIso8601String(),
                'source' => 'landing',
            ]);
            $msg = "¡Cita #{$op->id} solicitada! {$business->name} recibió tu reserva.";
        } else {
            $itemId = (int) $request->input('catalog_item_id', 0);
            $item = $business->catalogItems()->find($itemId);
            $qty = max(1, (int) $request->input('qty', 1));
            $items = $item
                ? [['name' => $item->name, 'qty' => $qty, 'price' => $item->price]]
                : [['name' => (string) $request->input('item_name', 'Pedido Web'), 'qty' => $qty, 'price' => 150]];

            $op = $odoo->registerSaleOrOrder($business, [
                'operation_type' => $business->branch === 'comandas' ? 'comanda' : 'sale',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'table_label' => $request->input('table_label'),
                'items' => $items,
                'source' => 'landing',
            ]);
            $msg = "¡Pedido #{$op->id} enviado a {$business->name}! Total: \$" . number_format($op->total_amount, 2);
        }

        $memory->recordPageEvent($business, 'landing_conversion', ['operation_id' => $op->id]);

        return redirect()->route('business.landing', ['slug' => $business->slug])
            ->with('landing_success', $msg);
    }
}
