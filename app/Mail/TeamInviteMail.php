<?php

namespace App\Mail;

use App\Models\CompanyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CompanyMember $member, public string $inviterName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->inviterName . ' invited you to ' . $this->member->company->name . ' on Apna Invoice');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-invite', with: [
            'company' => $this->member->company->name,
            'inviter' => $this->inviterName,
            'role' => $this->member->roleLabel(),
            'roleHelp' => CompanyMember::ROLES[$this->member->role]['help'] ?? '',
            'url' => route('team.join', $this->member->token),
        ]);
    }
}
