<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\Customer;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Models\SystemNotification;

class SendMonthlyDueReminders extends Command
{
    protected $signature = 'monthly:due-reminders';
    protected $description = 'Send monthly due reminders to customers with outstanding balances';

    public function handle(): int
    {
        $enabled = (bool) Setting::get('monthly_reminder_enabled', false);
        if (!$enabled) {
            $this->info('Monthly reminders are disabled.');
            return self::SUCCESS;
        }

        $day = (int) Setting::get('monthly_reminder_day', 1);
        $today = now();
        if ((int)$today->day !== $day) {
            $this->info('Not the configured reminder day ('.$day.'). Skipping.');
            return self::SUCCESS;
        }

        $channel = Setting::get('monthly_reminder_channel', 'sms');
        $template = Setting::get('monthly_reminder_template', 'Dear {name}, your total due is {due}. Please settle by {date}.');
        $excluded = (array) Setting::get('monthly_reminder_excluded', []);

        $customers = Customer::with(['sales' => function ($q) {
            $q->where('due_amount', '>', 0);
        }])->whereNotIn('id', $excluded)->get();

        $targets = $customers->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'due' => $c->sales->sum('due_amount'),
            ];
        })->filter(fn($x) => $x['due'] > 0 && !empty($x['phone']));

        $dateStr = $today->toDateString();
        $currency = Setting::get('currency', 'Rs');
        $decimalPlaces = (int) Setting::get('decimal_places', 2);

        $sms = new SmsService();
        $wa = new WhatsAppService();
        $smsSent = 0; $waLinks = [];

        foreach ($targets as $t) {
            $msg = str_replace(['{name}', '{due}', '{date}'], [
                $t['name'], number_format($t['due'], $decimalPlaces).' '.$currency, $dateStr
            ], $template);

            if (in_array($channel, ['sms','both'])) {
                if ($sms->send($t['phone'], $msg)) { $smsSent++; }
            }
            if (in_array($channel, ['whatsapp','both'])) {
                $waLinks[] = $wa->generateLink($t['phone'], $msg);
            }
        }

        SystemNotification::create([
            'user_id' => auth()->id(),
            'type' => 'monthly_due_reminder_sent',
            'title' => 'Monthly Due Reminders sent (auto)',
            'message' => 'Automated due reminders dispatched by scheduler',
            'data' => [
                'sms_sent' => $smsSent,
                'whatsapp_links' => $waLinks,
                'customer_count' => count($targets),
                'channel' => $channel,
            ],
        ]);

        $this->info('Monthly due reminders sent. SMS: '.$smsSent.'; WA Links: '.count($waLinks));
        return self::SUCCESS;
    }
}
