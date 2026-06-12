<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'topic' => ['required', Rule::in(['Membership', 'Events', 'Partnership', 'Support'])],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        // Best-effort database copy; the email is the primary delivery and
        // should not fail because of a storage problem.
        try {
            ContactMessage::create($validated);
        } catch (\Throwable $e) {
            Log::warning('Could not store contact message', ['error' => $e->getMessage()]);
        }

        Notification::route('mail', (string) config('services.contact.recipient'))
            ->notify(new ContactMessageReceived(
                name: $validated['name'],
                email: $validated['email'],
                topic: $validated['topic'],
                message: $validated['message'],
            ));

        return response()->json(['ok' => true], 201);
    }
}
