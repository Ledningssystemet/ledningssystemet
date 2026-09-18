<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class NotificationEmail extends Mailable
{
    use Queueable, SerializesModels;
    
    protected $user;
    protected $title;
    protected $messagecontent;
    protected $url;
    protected $sender;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $title, $messagecontent, $url, $sender)
    {
       $this->user = $user;
       $this->title = $title;
       $this->messagecontent = $messagecontent;
       $this->url = $url;
       $this->sender = $sender;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notification from '.config('ledningssystemet.application_name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            with: [
               'sender' => $this->sender,
               'title' => $this->title,
               'messagecontent' => $this->messagecontent,
               'url' => $this->url,
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
