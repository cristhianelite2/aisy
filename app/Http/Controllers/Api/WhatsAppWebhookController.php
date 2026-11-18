<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Ai\AisyAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, string $slug, AisyAssistantService $assistant): JsonResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();

        $result = $assistant->handleOwnerInput($business, [
            'text' => $request->input('text'),
            'audio_base64' => $request->input('audio_base64'),
            'audio_mime' => $request->input('audio_mime'),
            'location' => $request->input('location'),
            'sender_phone' => (string) $request->input('sender_phone', ''),
            'channel' => 'whatsapp',
        ]);

        return response()->json($result);
    }
}
