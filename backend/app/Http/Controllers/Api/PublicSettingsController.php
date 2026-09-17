<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $stored = Setting::where('is_public', true)->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->typedValue()]);

        return response()->json(['data' => collect(['business_name' => 'ARK med Pharmacy & Clinic', 'ordering_enabled' => true, 'whatsapp_enabled' => filled(config('services.whatsapp.number')), 'price_confirmation_text' => 'Price on confirmation'])->merge($stored)]);
    }
}
