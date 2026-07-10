<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RequestReviewed extends Notification
{
    public function __construct(private readonly CashAdvanceRequest|LeaveRequest $requestModel) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        $type = $this->requestModel instanceof CashAdvanceRequest ? 'Cash advance request' : 'Leave request';
        $decision = $this->requestModel->status === $this->requestModel::STATUS_APPROVED ? 'approved' : 'rejected';

        $title = "{$type} {$decision}";
        $body = $this->requestModel->review_note
            ? "Note: {$this->requestModel->review_note}"
            : "Your {$type} was {$decision}.";

        return (new WebPushMessage)
            ->title($title)
            ->icon('/pwa-192x192.png')
            ->body($body)
            ->data(['url' => '/my-requests']);
    }
}
