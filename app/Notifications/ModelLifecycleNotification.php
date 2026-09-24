<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ModelLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $url = '',
        protected ?User $sender = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Notification from '.config('ledningssystemet.application_name'))
            ->view('mail.notification', [
                'sender' => $this->sender,
                'title' => $this->title,
                'messagecontent' => $this->message,
                'url' => $this->url,
            ]);
    }
}
