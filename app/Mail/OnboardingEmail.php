<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public array $stageConfig;

    public function __construct(
        public User $user,
        public string $stage,
    ) {
        $this->stageConfig = (array) config("onboarding.stages.{$stage}", []);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->stageConfig['subject'] ?? 'Apna Invoice',
        );
    }

    public function content(): Content
    {
        $route = $this->stageConfig['cta_route'] ?? 'invoices.create';

        return new Content(
            view: 'emails.onboarding',
            with: [
                'user' => $this->user,
                'heading' => $this->stageConfig['heading'] ?? 'Apna Invoice',
                'lines' => $this->stageConfig['lines'] ?? [],
                'ctaLabel' => $this->stageConfig['cta'] ?? 'Open Apna Invoice',
                'ctaUrl' => route($route),
            ],
        );
    }
}
