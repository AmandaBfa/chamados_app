<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Convite para o sistema de chamados')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Seu acesso foi autorizado. Defina sua senha para aceitar o convite e confirmar este e-mail.')
            ->action('Aceitar convite', route('invitation.show', ['token' => $this->token]))
            ->line('Este convite é de uso único e expira em '.config('security.invitation_hours').' horas.')
            ->line('Se você não reconhece o convite, não o utilize.');
    }
}
