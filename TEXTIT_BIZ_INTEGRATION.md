# TextIt.biz SMS Integration

## Overview
The VehiclePOS system now supports TextIt.biz as the primary custom SMS provider for sending notifications to customers. This integration supports both REST API and HTTP GET methods.

## Features Implemented

### 1. Notification Settings Page
- **Location**: `/notifications/settings`
- **Route**: `Route::get('notifications/settings', [NotificationController::class, 'settings'])`
- **Features**:
  - SMS provider selection (TextIt.biz/Custom, Twilio, Nexmo, MSG91)
  - TextIt.biz as default and recommended provider
  - Dynamic form fields based on selected provider
  - Stock alert configuration
  - SMS notification toggles

### 2. SMS Settings Alert in Notifications
- **Location**: `resources/views/notifications/index.blade.php`
- **Features**:
  - Yellow alert banner when SMS is not configured
  - Direct link to notification settings
  - Recommends TextIt.biz for most businesses
  - Settings icon in notification header

### 3. TextIt.biz SMS Service Implementation
- **File**: `app/Services/SmsService.php`
- **Method**: `sendViaCustom()`
- **Supports**:
  - REST API: `https://api.textit.biz/`
  - HTTP GET: `https://www.textit.biz/sendmsg/index.php`
  - Basic Authentication (username/password)
  - Optional sender ID
  - Automatic method detection based on URL

## TextIt.biz API Configuration

### REST API Method (Recommended)
```
URL: https://api.textit.biz/
Method: POST
Headers: Authorization: Basic base64(username:password)
Body: {
  "to": "phone_number",
  "text": "message",
  "from": "sender_id" (optional)
}
```

### HTTP GET Method (Legacy)
```
URL: https://www.textit.biz/sendmsg/index.php
Method: GET
Parameters:
  - id: API username
  - pw: API password
  - to: phone number
  - text: message content
  - from: sender ID (optional)
```

## Setup Instructions

### For Users:
1. Navigate to **Notifications** from the main menu
2. Click **Configure SMS Settings** from the alert banner or **Settings** icon
3. Enable **SMS Notifications**
4. Select **TextIt.biz (Custom API)** as provider
5. Enter your TextIt.biz credentials:
   - API Endpoint URL (default: `https://api.textit.biz/`)
   - Username/API ID
   - Password/API Key
   - Sender ID (optional, max 11 characters)
6. Click **Save Settings**

### For Developers:
The system automatically detects the API method based on the URL:
- URLs containing `api.textit.biz` use REST API with JSON
- Other URLs use HTTP GET method

## Database Settings
The following settings are stored in the `settings` table:

| Key | Type | Description |
|-----|------|-------------|
| `sms_enabled` | boolean | Enable/disable SMS globally |
| `sms_provider` | text | Provider name (custom, twilio, nexmo, msg91) |
| `sms_custom_url` | text | TextIt.biz API endpoint URL |
| `sms_custom_username` | text | TextIt.biz API username/ID |
| `sms_custom_password` | text | TextIt.biz API password/key |
| `sms_sender_id` | text | Sender name (max 11 chars) |
| `enable_sms_notifications` | boolean | Enable SMS in notification system |
| `enable_stock_alerts` | boolean | Enable low stock alerts |
| `stock_alert_threshold` | integer | Stock quantity threshold |

## Usage in Code

### Sending SMS
```php
use App\Services\SmsService;

$smsService = new SmsService();

// Send single SMS
$smsService->send('+1234567890', 'Your message here');

// Send bulk SMS
$numbers = ['+1234567890', '+0987654321'];
$smsService->sendBulk($numbers, 'Bulk message');

// Test connection
$result = $smsService->testConnection('+1234567890');
```

## Routes Added

| Method | URI | Name | Controller Method |
|--------|-----|------|-------------------|
| GET | `/notifications/settings` | notifications.settings | NotificationController@settings |
| POST | `/notifications/settings/save` | notifications.settings.save | NotificationController@saveSettings |

## Files Modified/Created

### Created:
1. `resources/views/notifications/settings.blade.php` - Settings page with provider forms

### Modified:
1. `app/Http/Controllers/NotificationController.php`
   - Added `settings()` method
   - Added `saveSettings()` method
   
2. `app/Services/SmsService.php`
   - Added custom provider properties
   - Added `sendViaCustom()` method
   - Updated `send()` to handle custom provider
   - Enhanced `testConnection()` validation

3. `resources/views/notifications/index.blade.php`
   - Added SMS configuration alert
   - Added settings link in header

4. `routes/web.php`
   - Added notification settings routes

## Testing

### Manual Testing Steps:
1. Access `/notifications/settings`
2. Select "TextIt.biz (Custom API)" as provider
3. Enter valid TextIt.biz credentials
4. Save settings
5. Navigate to `/notifications/send`
6. Send a test promotion to verify SMS delivery

### Error Handling:
- Logs all SMS attempts with provider, status, and errors
- Gracefully handles missing configuration
- Validates provider-specific required fields
- Shows user-friendly error messages

## Benefits of TextIt.biz Integration

1. **Cost-Effective**: Competitive pricing for SMS delivery
2. **Flexible APIs**: Both REST and GET methods supported
3. **Simple Authentication**: Basic Auth or URL parameters
4. **Reliable Delivery**: Enterprise-grade SMS gateway
5. **Custom Sender ID**: Personalize messages with business name
6. **Easy Setup**: Minimal configuration required

## Troubleshooting

### SMS Not Sending
- Check if "Enable SMS Notifications" is enabled
- Verify TextIt.biz credentials are correct
- Check logs in `storage/logs/laravel.log`
- Ensure phone numbers include country code

### Settings Not Saving
- Check form validation errors
- Verify CSRF token is present
- Check database connection

### API Errors
- Verify API endpoint URL is correct
- Check authentication credentials
- Test with TextIt.biz dashboard first
- Review error logs for detailed messages

## Future Enhancements

Potential improvements:
- SMS template management
- Scheduled SMS campaigns
- SMS delivery reports
- Character count and cost estimation
- Multiple sender IDs per campaign
- SMS history tracking
- Delivery status webhooks

## Support

For TextIt.biz API documentation and support:
- Website: https://www.textit.biz
- API Documentation: Contact TextIt.biz support
- Dashboard: Login to view API credentials and usage

For VehiclePOS support:
- Check application logs: `storage/logs/laravel.log`
- Review this documentation
- Contact system administrator
