<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'admin_new_order',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_email' => $this->order->buyer_email,
            'message' => 'A new order has been placed.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Order Received')
            ->line('A new order has been placed.')
            ->line('Order Number: '.$this->order->order_number)
            ->line('Customer Email: '.$this->order->buyer_email)
            ->line('Total: ₱'.number_format((float) $this->order->total_amount, 2));
    }
}
