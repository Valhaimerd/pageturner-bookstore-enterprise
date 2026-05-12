<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewReviewNotification extends Notification
{
    use Queueable;

    public function __construct(public Review $review)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'admin_new_review',
            'review_id' => $this->review->id,
            'book_id' => $this->review->book_id,
            'rating' => $this->review->rating,
            'message' => 'A new review was submitted.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Review Submitted')
            ->line('A new review was submitted.')
            ->line('Rating: '.$this->review->rating.'/5')
            ->line('Comment: '.($this->review->comment ?: 'No comment.'));
    }
}
