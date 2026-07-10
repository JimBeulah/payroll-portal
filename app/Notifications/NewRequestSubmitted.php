<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewRequestSubmitted extends Notification
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
        $employeeName = $this->requestModel->employee->name;

        if ($this->requestModel instanceof CashAdvanceRequest) {
            $title = 'New cash advance request';
            $body = sprintf('%s requested ₱%s', $employeeName, number_format((float) $this->requestModel->amount, 2));
        } else {
            $title = 'New leave request';
            $body = sprintf(
                '%s requested leave from %s to %s',
                $employeeName,
                $this->requestModel->start_date->format('M j'),
                $this->requestModel->end_date->format('M j'),
            );
        }

        return (new WebPushMessage)
            ->title($title)
            ->icon('/pwa-192x192.png')
            ->body($body)
            ->data(['url' => '/approvals']);
    }
}
