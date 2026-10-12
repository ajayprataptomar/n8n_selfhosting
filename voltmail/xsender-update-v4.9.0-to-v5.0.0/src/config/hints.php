<?php

/*
|--------------------------------------------------------------------------
| In-App Hints (v4.6)
|--------------------------------------------------------------------------
|
| Contextual help shown next to forms, tables, and empty states across the
| panel. Each entry is keyed by a stable slug used in Blade with:
|
|   <x-hint-banner key="gateway.tracking_domain.cname_setup" />
|
| Authoring rules:
|   - Lead with the user's question, not the feature name.
|   - One concrete action per hint. Long explanations belong in docs.
|   - Use HTML; it is rendered verbatim.
|   - Keys are stable forever — never reuse, never rename. Add new keys
|     instead so old hint_overrides keep matching.
|
| Future (Phase 3): user-editable hints live in the `hint_overrides` table
| and override the `default_text` here per active locale.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway → Tracking Domain
    |--------------------------------------------------------------------------
    | Source ticket: mailpad.app — customer entered full subdomain in cPanel,
    | got "track.mailpad.app.mailpad.app" double-domain that never resolves.
    */
    'gateway.tracking_domain.cname_setup' => [
        'surface'      => 'banner',
        'audience'     => 'both',
        'severity'     => 'warning',
        'title'        => 'Common mistake when adding the CNAME',
        'default_text' => '<p class="mb-2">If your DNS provider is cPanel (or most shared hosts), the <strong>Name</strong> field auto-appends your zone domain. Enter <strong>just</strong> the subdomain prefix (e.g. <code>track</code>), <strong>not</strong> the full <code>track.yourdomain.com</code>.</p>'
            . '<p class="mb-0"><small>Entering the full domain produces <code>track.yourdomain.com.yourdomain.com</code> — a double-domain that will never resolve and the Verify button will always fail.</small></p>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings → Cron / Queue
    |--------------------------------------------------------------------------
    | Recurring ticket: "I scheduled a campaign but it stays at 0%."
    | Almost always: the per-minute cron is not registered.
    */
    'settings.cron.setup' => [
        'surface'      => 'banner',
        'audience'     => 'admin',
        'severity'     => 'info',
        'title'        => 'Cron is required for scheduled campaigns + automation',
        'default_text' => '<p class="mb-2">Without the per-minute cron, scheduled campaigns stay at 0% and automation workflows never run. Add this <strong>one line</strong> to your server\'s crontab (cPanel → Cron Jobs):</p>'
            . '<pre class="bg-light p-2 rounded mb-2"><code>* * * * * cd /path/to/xsender &amp;&amp; /usr/local/bin/php src/artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>'
            . '<p class="mb-0"><small>Replace <code>/path/to/xsender</code> with your install path and <code>/usr/local/bin/php</code> with the PHP binary your host uses (cPanel shows it on the PHP version page). After saving, schedule a test campaign 2 minutes in the future and confirm it runs.</small></p>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway → Android SMS — Add Session (the QR-code modal)
    |--------------------------------------------------------------------------
    | Recurring ticket: "I cannot find the XSender Android APK anywhere."
    | The APK ships as a sibling of the installer ZIP in the CodeCanyon bundle,
    | not inside it — buyers often miss it.
    */
    /*
    |--------------------------------------------------------------------------
    | Automation → Workflow Create (?preset=auto_reply)
    |--------------------------------------------------------------------------
    | Source ticket: prospects asking "where is the auto-reply bot?" — the
    | capability exists (Contact Replied trigger + AI Auto Reply action) but
    | sits 4 clicks deep inside the workflow builder. The sidebar quick-link
    | sends them here with `?preset=auto_reply`, and this banner tells them
    | which two nodes to drag.
    */
    'automation.workflow.auto_reply_quickstart' => [
        'surface'      => 'banner',
        'audience'     => 'both',
        'severity'     => 'info',
        'title'        => 'Build a WhatsApp Auto-Reply Bot in 30 seconds',
        'default_text' => '<p class="mb-2">Drag these two nodes onto the canvas, connect them, save:</p>'
            . '<ol class="mb-2">'
            . '<li><strong>Trigger → Contact Replied</strong> — fires every time someone messages you on WhatsApp.</li>'
            . '<li><strong>Action → AI Auto Reply</strong> — generates a context-aware reply and sends it back automatically.</li>'
            . '</ol>'
            . '<p class="mb-0"><small>For keyword-based replies instead of AI, drop a <strong>Condition</strong> node between them (e.g. <em>message contains "price"</em>) → branch to a <strong>Send WhatsApp</strong> action with your canned reply.</small></p>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin → Payment Gateways → Paystack (edit)
    |--------------------------------------------------------------------------
    | Source ticket: payment cleared on Paystack but subscription never
    | activated, because the configured currency_code didn't match Paystack's
    | settlement currency. The check is exact-string equality, so a tiny
    | mismatch silently fails the activation step.
    */
    'payment.paystack.currency_match' => [
        'surface'      => 'banner',
        'audience'     => 'admin',
        'severity'     => 'warning',
        'title'        => 'Paystack: the Currency field must match your Paystack account exactly',
        'default_text' => '<p class="mb-2">Paystack only settles in the currencies your account supports (typically <strong>NGN, GHS, ZAR, KES, USD</strong>). The <strong>Payment Gateway Currency</strong> below must be the exact ISO code Paystack returns on its transactions — otherwise XSender will verify the payment as paid but skip the subscription activation step (the user is charged but their plan stays inactive).</p>'
            . '<p class="mb-0"><small>Confirm by opening <a href="https://dashboard.paystack.com/#/transactions" target="_blank" rel="noopener">dashboard.paystack.com → Transactions</a> and matching its <em>Currency</em> column to the field below. As of v4.6.0 currency comparison is case-insensitive and amount comparison allows ±0.01 tolerance, but the ISO code itself still has to match.</small></p>',
    ],

    'gateway.android.apk_download' => [
        'surface'      => 'banner',
        'audience'     => 'both',
        'severity'     => 'info',
        'title'        => 'Where is the Android app (APK)?',
        'default_text' => '<p class="mb-2">The XSender Android Gateway APK is <strong>not</strong> inside the installer ZIP. It ships as a sibling file inside the <strong>"All Files &amp; Documentation"</strong> download from CodeCanyon:</p>'
            . '<pre class="bg-light p-2 rounded mb-2"><code>xsender-sms-gateway-app-vX.Y.apk     ← the Android app\nGet_SMS_Gateway_APK.md               ← install + pairing guide\nxsender-installer-vX.Y.zip           ← what you already installed</code></pre>'
            . '<p class="mb-0"><small>If you only downloaded the installer ZIP, go back to <strong>CodeCanyon → Downloads → All Files &amp; Documentation</strong> and re-download the full bundle. No extra purchase needed. After installing, point the app at this QR code to pair.</small></p>',
    ],

];
