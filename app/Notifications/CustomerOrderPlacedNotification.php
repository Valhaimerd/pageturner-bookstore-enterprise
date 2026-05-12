<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerOrderPlacedNotification extends Notification
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
            'type' => 'customer_order_placed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'receipt_number' => $this->order->receipt_number,
            'message' => 'Your order has been placed successfully.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Placed Successfully')
            ->line('Your order has been placed successfully.')
            ->line('Order Number: '.$this->order->order_number)
            ->line('Receipt Number: '.$this->order->receipt_number)
            ->line('Total: ₱'.number_format((float) $this->order->total_amount, 2));
    }
}
