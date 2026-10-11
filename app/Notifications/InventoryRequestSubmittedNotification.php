<?php

namespace App\Notifications;

use App\Mail\Clients\InventoryRequestMail;
use App\Models\InventoryRequest;
use App\Services\Inventory\InventoryRequestPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * To the team: a client asked for a change to a verified product.
 */
class InventoryRequestSubmittedNotification extends Notification
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
            alert: 'info',
            buttonLabel: 'Open Requests',
            buttonUrl: rtrim(config('app.url'), '/') . $this->url(),
        ))->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => $this->title(),
            'message'    => $this->message(),
            'icon'       => 'package',
            'action_url' => $this->url(),
        ];
    }

    private function title(): string
    {
        return $this->request->type === InventoryRequest::STOCK
            ? 'Stock Update Requested'
            : 'Product Change Requested';
    }

    private function message(): string
    {
        $product = $this->request->product;

        return "{$this->request->client?->company_name} asked to change "
            . InventoryRequestPresenter::summary($this->request)
            . " on \"{$product?->name}\" ({$product?->product_code}).";
    }

    private function url(): string
    {
        return '/requests?tab=' . $this->request->type;
    }
}
