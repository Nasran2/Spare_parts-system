@php
    $letterheadShop = is_array($shop ?? null) ? $shop : [];
    $letterheadSettings = \App\Models\Setting::query()->whereIn('key', [
        'shop_name',
        'shop_tagline',
        'shop_address',
        'shop_phone',
        'shop_email',
        'shop_logo',
        'preorder_pdf_line_color',
        'preorder_pdf_text_color',
        'preorder_pdf_heading_color',
        'preorder_pdf_logo_shape',
    ])->pluck('value', 'key');
    $letterheadSetting = static fn (string $key, mixed $fallback = '') => $letterheadSettings->get($key, $fallback);

    $letterheadName = $letterheadShop['name'] ?? $letterheadSetting('shop_name', config('app.name', 'Vehicle POS'));
    $letterheadTagline = $letterheadShop['tagline'] ?? $letterheadSetting('shop_tagline', '');
    $letterheadAddress = $letterheadShop['address'] ?? $letterheadSetting('shop_address', '');
    $letterheadPhone = $letterheadShop['phone'] ?? $letterheadSetting('shop_phone', '');
    $letterheadEmail = $letterheadShop['email'] ?? $letterheadSetting('shop_email', '');
    $letterheadLogo = $letterheadShop['logo'] ?? $letterheadSetting('shop_logo', '');

    $sanitizePdfColor = static function (mixed $color, string $fallback): string {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback;
    };

    $letterheadLineColor = $sanitizePdfColor($letterheadSetting('preorder_pdf_line_color', '#9b2c20'), '#9b2c20');
    $letterheadTextColor = $sanitizePdfColor($letterheadSetting('preorder_pdf_text_color', '#172033'), '#172033');
    $letterheadHeadingColor = $sanitizePdfColor($letterheadSetting('preorder_pdf_heading_color', '#f6b4b4'), '#f6b4b4');
    $letterheadLogoShape = (string) $letterheadSetting('preorder_pdf_logo_shape', 'original');
    $letterheadLogoPath = $letterheadLogo ? public_path(ltrim((string) $letterheadLogo, '/')) : null;
    $letterheadHasLogo = $letterheadLogoPath && is_file($letterheadLogoPath);
    $letterheadLogoStyle = match ($letterheadLogoShape) {
        'round' => 'width:58px;height:58px;border-radius:50%;object-fit:cover;',
        'square' => 'width:58px;height:58px;border-radius:0;object-fit:cover;',
        'box' => 'width:64px;height:58px;border-radius:7px;object-fit:cover;',
        default => 'max-width:82px;max-height:62px;width:auto;height:auto;',
    };
    $documentTitle = trim((string) ($documentTitle ?? 'Report'));
    $documentReference = trim((string) ($documentReference ?? ''));
    $documentMeta = is_array($documentMeta ?? null) ? $documentMeta : [];
    $showGeneratedAt = $showGeneratedAt ?? true;
@endphp

<style>
    body { color: {{ $letterheadTextColor }}; }
    table thead th {
        background: {{ $letterheadHeadingColor }} !important;
        color: {{ $letterheadLineColor }} !important;
        border-color: #cfd5dc !important;
    }
    .pdf-letterhead-wrap { margin: 0 0 14px; border-bottom: 2px solid {{ $letterheadLineColor }}; }
    .pdf-letterhead { width: 100%; border-collapse: collapse; margin: 0; }
    .pdf-letterhead td { border: none !important; padding: 0 !important; vertical-align: top; }
    .pdf-letterhead td.pdf-letterhead-logo { width: 92px; padding-right: 12px !important; vertical-align: middle !important; }
    .pdf-letterhead-title { margin: 0 0 4px; color: {{ $letterheadLineColor }}; font-size: 23px; line-height: 1.05; font-weight: bold; text-transform: uppercase; }
    .pdf-letterhead-name { color: {{ $letterheadTextColor }}; font-size: 16px; line-height: 1.2; font-weight: bold; }
    .pdf-letterhead-tagline, .pdf-letterhead-contact, .pdf-letterhead-meta { color: #667085; font-size: 10px; line-height: 1.45; }
    .pdf-letterhead-contact { word-wrap: break-word; }
    .pdf-letterhead td.pdf-letterhead-meta { width: 32%; text-align: right; padding-left: 10px !important; }
    .pdf-letterhead-reference { display: inline-block; margin-bottom: 3px; padding: 3px 9px; border: 1px solid #64748b; border-radius: 10px; color: {{ $letterheadTextColor }}; font-size: 11px; }
    .pdf-letterhead-contact { padding: 7px 0 8px; }
</style>

<div class="pdf-letterhead-wrap">
    <table class="pdf-letterhead" role="presentation">
        <tr>
            @if($letterheadHasLogo)
                <td class="pdf-letterhead-logo">
                    <img src="{{ $letterheadLogoPath }}" alt="{{ $letterheadName }} logo" style="{{ $letterheadLogoStyle }}">
                </td>
            @endif
            <td>
                <div class="pdf-letterhead-title">{{ $documentTitle }}</div>
                <div class="pdf-letterhead-name">{{ $letterheadName }}</div>
                @if($letterheadTagline)
                    <div class="pdf-letterhead-tagline">{{ $letterheadTagline }}</div>
                @endif
            </td>
            <td class="pdf-letterhead-meta">
                @if($documentReference)
                    <div class="pdf-letterhead-reference">{{ $documentReference }}</div>
                @endif
                @foreach($documentMeta as $label => $value)
                    @if($value !== null && $value !== '')
                        <div>@if(!is_int($label))<strong>{{ $label }}:</strong> @endif{{ $value }}</div>
                    @endif
                @endforeach
                @if($showGeneratedAt)
                    <div><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</div>
                @endif
            </td>
        </tr>
    </table>
    <div class="pdf-letterhead-contact">
        @if($letterheadAddress){{ $letterheadAddress }}@endif
        @if($letterheadPhone){{ $letterheadAddress ? ' | ' : '' }}{{ $letterheadPhone }}@endif
        @if($letterheadEmail){{ ($letterheadAddress || $letterheadPhone) ? ' | ' : '' }}{{ $letterheadEmail }}@endif
    </div>
</div>
