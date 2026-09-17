<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'mobile' => preg_replace('/[\s()-]/', '', (string) $request->input('mobile')),
            'email' => filled($request->input('email')) ? strtolower(trim($request->input('email'))) : null,
            'message' => filled($request->input('message')) ? trim($request->input('message')) : null,
        ]);
        $data = $request->validate(['type' => 'required|in:contact,doctor_consultation,health_checkup', 'name' => 'required|string|min:2|max:120', 'mobile' => 'required|string|regex:/^\+?[0-9]{10,13}$/', 'email' => 'nullable|email|max:255', 'message' => 'nullable|string|max:2000', 'payload' => 'nullable|array|max:10', 'consent' => 'accepted', 'website' => 'nullable|prohibited']);
        $enquiry = Enquiry::create(['type' => $data['type'], 'name' => $data['name'], 'mobile' => $data['mobile'], 'email' => $data['email'] ?? null, 'message' => $data['message'] ?? null, 'payload_json' => $data['payload'] ?? null, 'status' => 'new']);

        return response()->json(['data' => ['reference' => 'ENQ-'.str_pad((string) $enquiry->id, 6, '0', STR_PAD_LEFT), 'status' => $enquiry->status, 'type' => $enquiry->type], 'message' => 'Your enquiry has been received. ARK med will contact you.'], 201);
    }
}
