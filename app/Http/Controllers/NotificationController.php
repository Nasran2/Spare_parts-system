<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemNotification;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\SmsService;
use App\Services\WhatsAppService;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = SystemNotification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $unreadCount = $notifications->whereNull('read_at')->count();
        
        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function settings(Request $request)
    {
        $settings = [
            'sms_enabled' => Setting::get('sms_enabled', false),
            'sms_provider' => Setting::get('sms_provider', 'custom'),
            'sms_api_key' => Setting::get('sms_api_key', ''),
            'sms_api_secret' => Setting::get('sms_api_secret', ''),
            'sms_sender_id' => Setting::get('sms_sender_id', ''),
            'sms_account_sid' => Setting::get('sms_account_sid', ''),
            'sms_custom_url' => Setting::get('sms_custom_url', 'https://api.textit.biz/'),
            'sms_custom_username' => Setting::get('sms_custom_username', ''),
            'sms_custom_password' => Setting::get('sms_custom_password', ''),
            'stock_alert_threshold' => (int) Setting::get('stock_alert_threshold', 10),
            'enable_stock_alerts' => Setting::get('enable_stock_alerts', true),
            'enable_sms_notifications' => Setting::get('enable_sms_notifications', false),
            // Monthly Due Reminder
            'monthly_reminder_enabled' => (bool) Setting::get('monthly_reminder_enabled', false),
            'monthly_reminder_day' => (int) Setting::get('monthly_reminder_day', 1),
            'monthly_reminder_time' => Setting::get('monthly_reminder_time', '09:00'),
            'monthly_reminder_channel' => Setting::get('monthly_reminder_channel', 'sms'),
            'monthly_reminder_template' => Setting::get('monthly_reminder_template', 'Dear {name}, your total due is {due}. Please settle by {date}. Thank you.'),
            'monthly_reminder_excluded' => (array) Setting::get('monthly_reminder_excluded', []),
        ];
        $customers = Customer::orderBy('name')->get(['id','name','phone','is_active']);
        return view('notifications.settings', compact('settings','customers'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'sms_enabled' => 'nullable|boolean',
            'sms_provider' => 'nullable|string|in:twilio,nexmo,msg91,custom',
            'sms_api_key' => 'nullable|string|max:500',
            'sms_api_secret' => 'nullable|string|max:500',
            'sms_sender_id' => 'nullable|string|max:50',
            'sms_account_sid' => 'nullable|string|max:500',
            'sms_custom_url' => 'nullable|url|max:500',
            'sms_custom_username' => 'nullable|string|max:100',
            'sms_custom_password' => 'nullable|string|max:100',
            'stock_alert_threshold' => 'nullable|integer|min:0|max:10000',
            'enable_stock_alerts' => 'nullable|boolean',
            'enable_sms_notifications' => 'nullable|boolean',
            // Monthly Due Reminder
            'monthly_reminder_enabled' => 'nullable|boolean',
            'monthly_reminder_day' => 'nullable|integer|min:1|max:31',
            'monthly_reminder_time' => 'nullable|regex:/^\d{2}:\d{2}$/',
            'monthly_reminder_channel' => 'nullable|string|in:sms,whatsapp,both',
            'monthly_reminder_template' => 'nullable|string|max:500',
            'monthly_reminder_excluded' => 'nullable|array',
            'monthly_reminder_excluded.*' => 'integer|exists:customers,id',
        ]);

        foreach ($validated as $key => $value) {
            $booleanKeys = ['sms_enabled', 'enable_stock_alerts', 'enable_sms_notifications', 'monthly_reminder_enabled'];
            $jsonKeys = ['monthly_reminder_excluded'];
            $type = in_array($key, $booleanKeys) ? 'boolean' : (in_array($key, $jsonKeys) ? 'json' : 'text');
            Setting::set($key, $value, $type, 'notifications');
        }

        return back()->with('success', 'Notification settings saved successfully!');
    }

    public function history(Request $request)
    {
        $promotions = SystemNotification::where('type', 'promotion_sent')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        
        return view('notifications.history', compact('promotions'));
    }

    public function sendForm(Request $request)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'is_active']);

        // Top 5 customers by total spend (sales total_amount)
        $topCustomers = Customer::select('customers.id','customers.name','customers.phone')
            ->leftJoin('sales','sales.customer_id','=','customers.id')
            ->groupBy('customers.id','customers.name','customers.phone')
            ->selectRaw('COALESCE(SUM(sales.total_amount),0) as total_spend')
            ->orderByDesc('total_spend')
            ->take(5)
            ->get();
        
        return view('notifications.send', compact('customers','topCustomers'));
    }

    public function markRead(Request $request, $id)
    {
        $n = SystemNotification::where('user_id', Auth::id())->findOrFail($id);
        $n->update(['read_at' => now()]);
        return back()->with('success', 'Notification marked as read');
    }

    public function sendPromotion(Request $request)
    {
        $data = $request->validate([
            'channel' => 'required|in:sms,whatsapp,both',
            'message' => 'required|string|max:500',
            'customer_scope' => 'nullable|in:all,active,selected',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $scope = $data['customer_scope'] ?? 'all';
        $customersQuery = Customer::query();
        
        if ($scope === 'selected' && !empty($data['customer_ids'])) {
            $customersQuery->whereIn('id', $data['customer_ids']);
        } elseif ($scope === 'active') {
            $customersQuery->where('is_active', true);
        } elseif ($scope === 'top5') {
            // Restrict to top 5 spenders
            $top5 = Customer::select('customers.id')
                ->leftJoin('sales','sales.customer_id','=','customers.id')
                ->groupBy('customers.id')
                ->selectRaw('COALESCE(SUM(sales.total_amount),0) as total_spend')
                ->orderByDesc('total_spend')
                ->take(5)
                ->pluck('customers.id');
            $customersQuery->whereIn('id', $top5);
        }
        
        $customers = $customersQuery->get(['id','name','phone']);
        $numbers = $customers->pluck('phone')->filter()->unique()->values()->all();

        $smsSent = $waSent = 0;
        $waLinks = [];
        if (in_array($data['channel'], ['sms', 'both'])) {
            $smsSent = (new SmsService())->sendBulk($numbers, $data['message']);
        }
        if (in_array($data['channel'], ['whatsapp', 'both'])) {
            $waLinks = (new WhatsAppService())->sendBulk($numbers, $data['message']);
            $waSent = count($waLinks);
        }

        // Log system notification for sender
        if (Auth::check()) {
            SystemNotification::create([
            'user_id' => Auth::id(),
                'type' => 'promotion_sent',
                'title' => 'Promotion sent',
                'message' => $data['message'],
                'data' => [
                    'sms_sent' => $smsSent,
                    'whatsapp_sent' => $waSent,
                    'channel' => $data['channel'],
                    'customer_count' => count($numbers),
                    'whatsapp_links' => $waLinks,
                ],
            ]);
        }

        if (!empty($waLinks)) {
            session()->flash('whatsapp_links', $waLinks);
        }

        return back()->with('success', 'Promotion sent successfully.');
    }

    /**
     * Manually trigger monthly due reminders now.
     */
    public function sendMonthlyRemindersNow(Request $request)
    {
        $enabled = (bool) Setting::get('monthly_reminder_enabled', false);
        if (!$enabled) {
            return back()->with('error', 'Monthly due reminders are disabled in settings.');
        }

        $channel = Setting::get('monthly_reminder_channel', 'sms');
        $template = Setting::get('monthly_reminder_template', 'Dear {name}, your total due is {due}. Please settle by {date}.');
        $excluded = (array) Setting::get('monthly_reminder_excluded', []);

        // Build due customers list
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

        $dateStr = now()->toDateString();
        $currency = Setting::get('currency', 'Rs');
        $decimalPlaces = (int) Setting::get('decimal_places', 2);

        $sms = new SmsService();
        $wa = new WhatsAppService();
        $smsSent = 0; $waLinks = [];

        foreach ($targets as $t) {
            $msg = str_replace([
                '{name}', '{due}', '{date}'
            ], [
                $t['name'], number_format($t['due'], $decimalPlaces) . ' ' . $currency, $dateStr
            ], $template);

            if (in_array($channel, ['sms','both'])) {
                if ($sms->send($t['phone'], $msg)) { $smsSent++; }
            }
            if (in_array($channel, ['whatsapp','both'])) {
                $waLinks[] = $wa->generateLink($t['phone'], $msg);
            }
        }

        // Log system notification for operator
        if (auth()->check()) {
            SystemNotification::create([
                'user_id' => auth()->id(),
                'type' => 'monthly_due_reminder_sent',
                'title' => 'Monthly Due Reminders sent',
                'message' => 'Automated due reminders dispatched',
                'data' => [
                    'sms_sent' => $smsSent,
                    'whatsapp_links' => $waLinks,
                    'customer_count' => count($targets),
                    'channel' => $channel,
                ],
            ]);
        }

        if (!empty($waLinks)) {
            session()->flash('whatsapp_links', $waLinks);
        }

        return back()->with('success', 'Monthly due reminders sent: SMS='.$smsSent.'; WA Links=' . count($waLinks));
    }
}

