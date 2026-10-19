<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
    <style>
        body { font: 15px/1.6 Arial, sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 24px; }
        main { max-width: 1000px; margin: auto; background: white; padding: 28px; border: 1px solid #e2e8f0; border-radius: 14px; }
        h1 { color: {{ $success ? '#15803d' : '#b91c1c' }}; }
        pre { background: #f1f5f9; padding: 16px; white-space: pre-wrap; overflow-wrap: anywhere; }
        .error { border-left: 4px solid #dc2626; }
        @media (max-width: 600px) { body { padding: 10px; } main { padding: 16px; } }
    </style>
</head>
<body><main>
    <h1>{{ $heading }}</h1>
    <p><strong>Connection:</strong> {{ $connection }}<br><strong>Database:</strong> {{ $database }}</p>
    @if($error)<h2>Action required</h2><pre class="error">{{ $error }}</pre>@endif
    <h2>Preflight Repair</h2>
    <pre>{{ $repairs ? implode("\n", $repairs) : 'No preflight repair was needed.' }}</pre>
    <h2>Migration Output</h2>
    <pre>{{ $output ?: 'No output returned.' }}</pre>
    <h2>Database Verification</h2>
    <pre>{{ implode("\n", $verification) }}</pre>
    <p>The check verifies the required database structure. Missing records can only be recovered from a database backup.</p>
    <p>Repair version: <strong>{{ defined(\App\Services\MigrationHealthService::class.'::REPAIR_VERSION') ? constant(\App\Services\MigrationHealthService::class.'::REPAIR_VERSION') : 'Older repair code. Upload the complete latest package.' }}</strong></p>
</main></body>
</html>
