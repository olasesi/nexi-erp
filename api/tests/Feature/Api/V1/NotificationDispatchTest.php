<?php

use App\Channels\InAppChannel;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

uses(RefreshDatabase::class);

it('writes an in-app inbox row for a user notification', function () {
    Mail::fake();

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'email' => 'owner@example.com']);

    $invoice = Invoice::factory()->create(['company_id' => $company->id]);

    app(NotificationService::class)->toUser($user, new InvoiceOverdueNotification($invoice));

    $row = Notification::where('type', 'invoice.overdue')->sole();

    expect($row->user_id)->toBe($user->id)
        ->and($row->company_id)->toBe($company->id)
        ->and($row->data['invoice_number'])->toBe($invoice->invoice_number)
        ->and($row->isRead())->toBeFalse();
});

it('routes domain notifications to the inbox and mail channels', function () {
    NotificationFacade::fake();

    $user = User::factory()->make(['email' => 'owner@example.com']);

    $payment = new Payment([
        'payment_number' => 'REC-1',
        'amount' => 100,
        'invoice_id' => 5,
    ]);

    $user->notify(new PaymentReceivedNotification($payment));

    NotificationFacade::assertSentTo($user, PaymentReceivedNotification::class, function ($notification, $channels) {
        return in_array(InAppChannel::class, $channels, true)
            && in_array('mail', $channels, true);
    });
});

it('does not add the mail channel when the recipient has no email', function () {
    $notification = new PaymentReceivedNotification(new Payment(['payment_number' => 'REC-1', 'amount' => 10]));

    $withoutEmail = new User(['email' => null]);
    $withEmail = new User(['email' => 'someone@example.com']);

    expect($notification->via($withoutEmail))->toBe([InAppChannel::class])
        ->and($notification->via($withEmail))->toBe([InAppChannel::class, 'mail']);
});
