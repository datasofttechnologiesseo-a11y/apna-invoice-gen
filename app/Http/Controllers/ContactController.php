<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Rules\TurnstileValid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Captcha is enforced in production (rule fails closed); no-op in dev.
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ]);

        $to = config('services.contact.to')
            ?? env('CONTACT_TO_EMAIL')
            ?? config('mail.from.address');

        try {
            Mail::to($to)->send(new ContactMessage(
                fromName: $data['name'],
                fromEmail: $data['email'],
                subjectLine: $data['subject'],
                messageBody: $data['message'],
                phone: $data['phone'] ?? null,
            ));
        } catch (\Throwable $e) {
            Log::error('Contact form send failed: ' . $e->getMessage());
            return back()->withInput()->withErrors([
                'contact' => 'We couldn\'t send your message right now. Please try again in a few minutes.',
            ]);
        }

        return redirect()->route('pages.contact')
            ->with('status', 'Thanks — your message is on its way. We\'ll reply within one business day.');
    }
}
