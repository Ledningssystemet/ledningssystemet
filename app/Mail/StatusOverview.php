<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class StatusOverview extends Mailable
{
    use Queueable, SerializesModels;
    
    protected $rcptUser;
    protected $sections;
    protected $dueItems;

    /**
     * Create a new message instance.
     *
     * @param array<string, array> $sections Status issues grouped by section header
     * @param array<int, array{level: string, text: string}> $dueItems Overdue or near due items
     */
    public function __construct(User $user, array $sections = [], array $dueItems = [])
    {
       $this->rcptUser = $user;
       $this->sections = $sections;
       $this->dueItems = $dueItems;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Status Overview'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.statusoverview',
            text: 'mail.statusoverview-text',
            with: [
                'user' => $this->rcptUser,
                'sections' => $this->sections,
                'dueItems' => $this->dueItems,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
