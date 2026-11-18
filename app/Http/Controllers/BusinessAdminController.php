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
            'price' => (float) $request->input('price', 100),
            'category' => (string) $request->input('category', 'General'),
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
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
}
