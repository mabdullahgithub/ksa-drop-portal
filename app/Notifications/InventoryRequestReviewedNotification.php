<?php

namespace App\Notifications;

use App\Mail\Clients\InventoryRequestMail;
use App\Models\InventoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * To the client: the team approved or rejected the change they asked for.
 */
class InventoryRequestReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(private InventoryRequest $request) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): InventoryRequestMail
    {
        return (new InventoryRequestMail(
            $this->request,
            subjectLine: $this->title() . ': ' . $this->request->product?->name,
            headline: $this->title(),
            intro: $this->message(),
            alert: $this->approved() ? 'success' : 'warning',
            buttonLabel: 'View My Inventory',
            buttonUrl: rtrim(config('app.url'), '/') . '/portal/inventory?tab=requests',
        ))->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => $this->title(),
            'message'    => $this->message(),
            'icon'       => $this->approved() ? 'shield-check' : 'shield-x',
            'action_url' => '/portal/inventory?tab=requests',
        ];
    }

    private function approved(): bool
    {
        return $this->request->status === InventoryRequest::APPROVED;
    }

    private function title(): string
    {
        $what = $this->request->type === InventoryRequest::STOCK ? 'Stock Update' : 'Product Change';

        return $what . ($this->approved() ? ' Approved' : ' Rejected');
    }

    private function message(): string
    {
        $request = $this->request;
        $product = $request->product;
        $name = "\"{$product?->name}\" ({$product?->product_code})";

        if (! $this->approved()) {
            $what = $request->type === InventoryRequest::STOCK ? 'stock update' : 'changes';

            return "Your {$what} for {$name} were not approved."
                . ($request->rejection_reason ? ' Reason: ' . $request->rejection_reason : '');
        }

        if ($request->type === InventoryRequest::DETAILS) {
            return "Your changes to {$name} were approved and are now live.";
        }

        $units = abs((int) $request->received_quantity);

        return $request->received_quantity < 0
            ? "{$units} units were taken off {$name}. Stock is now {$request->stock_after}."
            : "{$units} units were received for {$name}. Stock is now {$request->stock_after}.";
    }
}
