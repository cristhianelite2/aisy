<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\Ai\AiMemoryService;
use App\Services\Ai\AisyAssistantService;
use App\Services\Ai\DeadTimeRecommendationService;
use App\Services\Integrations\GoogleMapsKitService;
use App\Services\Integrations\SellerCentralClient;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Odoo\OdooBusinessService;
use App\Services\Odoo\OdooClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessAdminController extends Controller
{
    private function authorizeBusiness(Request $request, Business $business): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        if ($business->user_id !== null && $business->user_id !== $user->id && !$user->is_admin) {
            abort(403, 'No tienes permiso para administrar este negocio.');
        }
    }

    public function dashboard(
        Request $request,
        string $slug,
        OdooBusinessService $odoo,
        AiMemoryService $memory,
        WhatsAppGatewayClient $whatsapp,
        SellerCentralClient $sellerCentral,
        GoogleMapsKitService $gmaps
    ): View {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $metrics = $odoo->getBusinessMetrics($business);
        $catalog = $business->catalogItems()->latest()->get();
        $operations = $business->operations()->latest()->limit(20)->get();
        $activities = $business->activities()->latest()->limit(20)->get();
        $episodicMemories = $business->episodicMemories()->latest()->limit(12)->get();
        $semanticMemory = $memory->getSemanticMemory($business);
        $waStatus = $whatsapp->getStatus($business);
        $scConnectUrl = $sellerCentral->getConnectSocialUrl($business);
        $gmapsKit = $business->gmaps_kit ?: $gmaps->generateKit($business);
        $featureLabels = Business::featureLabels();

        return view('admin.dashboard', compact(
            'business',
            'metrics',
            'catalog',
            'operations',
            'activities',
            'episodicMemories',
            'semanticMemory',
            'waStatus',
            'scConnectUrl',
            'gmapsKit',
            'featureLabels'
        ));
    }

    public function toggleFeature(Request $request, string $slug): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $feature = (string) $request->input('feature', '');
        $enabled = $request->has('enabled') ? (bool) $request->boolean('enabled') : null;

        $features = $business->toggleFeature($feature, $enabled);

        return response()->json([
            'ok' => true,
            'feature' => $feature,
            'enabled' => (bool) ($features[$feature] ?? false),
            'features' => $features,
        ]);
    }

    public function assistantChat(Request $request, string $slug, AisyAssistantService $assistant): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        $result = $assistant->handleOwnerInput($business, [
            'text' => $request->input('text'),
            'audio_base64' => $request->input('audio_base64'),
            'audio_mime' => $request->input('audio_mime'),
            'sender_phone' => $business->owner_phone,
            'channel' => 'admin_chat',
        ]);

        return response()->json($result);
    }

    public function whatsappQr(Request $request, string $slug, WhatsAppGatewayClient $whatsapp): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $whatsapp->createInstance($business);

        return response()->json($whatsapp->getQrCode($business));
    }

    public function triggerDeadTimes(Request $request, string $slug, DeadTimeRecommendationService $deadTime): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        return response()->json($deadTime->analyzeAndRecommend($business, true));
    }

    public function addCatalogItem(Request $request, string $slug, OdooBusinessService $odoo): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $item = $odoo->syncCatalogItem($business, [
            'name' => (string) $request->input('name', 'Nuevo Ítem'),
            'type' => (string) $request->input('type', $business->branch === 'tienda' ? 'product' : ($business->branch === 'comandas' ? 'consu' : 'service')),
            'price' => (float) $request->input('price', 100),
            'duration_minutes' => $request->filled('duration_minutes') ? (int) $request->input('duration_minutes') : null,
            'stock_qty' => $request->filled('stock_qty') ? (int) $request->input('stock_qty') : 10,
            'category' => (string) $request->input('category', 'General'),
            'description' => $request->input('description'),
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function updateCatalogItem(Request $request, string $slug, int $id, OdooClient $odoo): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $item = $business->catalogItems()->findOrFail($id);

        $item->fill($request->only(['name', 'type', 'price', 'duration_minutes', 'stock_qty', 'category', 'description']));
        if ($request->has('active')) {
            $item->active = (bool) $request->boolean('active');
        }
        $item->save();

        if ($item->odoo_product_id) {
            try {
                if ($odoo->isAvailable()) {
                    $odoo->write('product.template', [(int) $item->odoo_product_id], [
                        'name' => $item->name,
                        'list_price' => (float) $item->price,
                    ]);
                }
            } catch (\Throwable) {
                // Non-fatal
            }
        }

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function deleteCatalogItem(Request $request, string $slug, int $id): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $item = $business->catalogItems()->findOrFail($id);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function updateOperationStatus(Request $request, string $slug, int $id): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);
        $op = $business->operations()->findOrFail($id);
        $op->status = (string) $request->input('status', 'completed');
        $op->save();

        return response()->json(['ok' => true, 'operation' => $op]);
    }

    public function createAppointment(Request $request, string $slug, OdooBusinessService $odoo): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        $op = $odoo->scheduleAppointment($business, [
            'customer_name' => (string) $request->input('customer_name', 'Cliente'),
            'customer_phone' => (string) $request->input('customer_phone', ''),
            'service_name' => (string) $request->input('service_name', 'Servicio'),
            'scheduled_at' => $request->input('scheduled_at') ?: now()->addDay()->setTime(12, 0)->toIso8601String(),
            'source' => 'admin',
        ]);

        return response()->json(['ok' => true, 'operation' => $op]);
    }

    public function createPosSale(Request $request, string $slug, OdooBusinessService $odoo): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        $itemId = (int) $request->input('catalog_item_id', 0);
        $catalogItem = $itemId ? $business->catalogItems()->find($itemId) : null;
        $qty = max(1, (int) $request->input('qty', 1));
        $name = $catalogItem?->name ?: (string) $request->input('item_name', 'Venta Mostrador');
        $price = (float) ($request->input('price') ?: ($catalogItem?->price ?? 150));

        $op = $odoo->registerSaleOrOrder($business, [
            'operation_type' => $business->branch === 'comandas' ? 'comanda' : 'sale',
            'customer_name' => (string) $request->input('customer_name', 'Cliente Mostrador'),
            'customer_phone' => (string) $request->input('customer_phone', ''),
            'table_label' => $request->input('table_label'),
            'items' => [['name' => $name, 'qty' => $qty, 'price' => $price]],
            'source' => 'admin',
        ]);

        return response()->json(['ok' => true, 'operation' => $op]);
    }

    public function createPromotion(Request $request, string $slug, OdooBusinessService $odoo): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        $op = $odoo->createPromotion($business, [
            'title' => (string) $request->input('title', 'Promoción Especial'),
            'discount_percent' => (float) $request->input('discount_percent', 15),
            'description' => (string) $request->input('description', ''),
            'source' => 'admin',
        ]);

        return response()->json(['ok' => true, 'operation' => $op]);
    }

    public function saveLandingConfig(Request $request, string $slug): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $this->authorizeBusiness($request, $business);

        if ($request->has('advanced_mode')) {
            $business->advanced_mode = (bool) $request->boolean('advanced_mode');
        }
        if ($request->filled('primary_color')) {
            $business->primary_color = (string) $request->input('primary_color');
        }
        if ($request->filled('secondary_color')) {
            $business->secondary_color = (string) $request->input('secondary_color');
        }
        if ($request->filled('style_preset')) {
            $business->style_preset = (string) $request->input('style_preset');
        }

        if ($request->has('landing_config') && is_array($request->input('landing_config'))) {
            $current = $business->landing_config ?? [];
            $business->landing_config = array_merge($current, $request->input('landing_config'));
        }

        $business->save();

        return response()->json([
            'ok' => true,
            'advanced_mode' => $business->advanced_mode,
            'landing_config' => $business->landing_config,
        ]);
    }
}
