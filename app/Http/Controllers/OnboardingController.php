<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Provisioning\BusinessProvisionerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingController extends Controller
{
    public function index(): View
    {
        $businesses = Business::latest()->limit(12)->get();

        return view('onboarding.index', compact('businesses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_name' => 'required|string|max:120',
            'owner_phone' => 'required|string|max:32',
            'name' => 'required|string|max:120',
            'branch' => 'required|in:estetica,tienda,comandas',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'style_preset' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:255',
        ]);

        $slug = Business::generateUniqueSlug($data['name']);
        $phone = preg_replace('/\D+/', '', $data['owner_phone']);

        $user = $request->user();
        if (!$user) {
            $user = User::firstOrCreate(
                ['email' => ($phone ?: $slug) . '@aisy.local'],
                [
                    'name' => $data['owner_name'],
                    'password' => Hash::make(Str::random(24)),
                ]
            );
            Auth::login($user);
        }

        $business = Business::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'slug' => $slug,
            'branch' => $data['branch'],
            'owner_name' => $data['owner_name'],
            'owner_phone' => $phone,
            'address' => $data['address'] ?: 'Dirección por confirmar',
            'primary_color' => $data['primary_color'] ?: '#0ea5e9',
            'secondary_color' => $data['secondary_color'] ?: '#6366f1',
            'style_preset' => $data['style_preset'] ?: 'moderno',
            'features' => Business::defaultFeaturesForBranch($data['branch']),
            'wa_instance_name' => $slug,
            'provisioning_status' => 'pending',
        ]);

        return redirect()->route('onboarding.progress', ['slug' => $business->slug]);
    }

    public function progress(string $slug, WhatsAppGatewayClient $whatsapp): View
    {
        $business = Business::where('slug', $slug)->firstOrFail();
        $waStatus = $whatsapp->getStatus($business);

        return view('onboarding.progress', compact('business', 'waStatus'));
    }

    public function stream(string $slug, BusinessProvisionerService $provisioner): StreamedResponse
    {
        $business = Business::where('slug', $slug)->firstOrFail();

        return response()->stream(function () use ($business, $provisioner): void {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $send = static function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $provisioner->runAllSteps($business, function (array $step) use ($send): void {
                $send('step', $step);
            });

            $send('complete', [
                'slug' => $business->slug,
                'landing_url' => url("/{$business->slug}"),
                'admin_url' => url("/{$business->slug}/admin"),
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
