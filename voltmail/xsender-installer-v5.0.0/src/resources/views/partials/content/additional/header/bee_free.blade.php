@php
    $plugins = json_decode(site_settings('available_plugins') ?: '[]', true);
    $beeFree = $plugins['beefree'] ?? null;
@endphp

<meta name="bee-endpoint" content="https://auth.getbee.io/loginV2">
<meta name="bee-client-id" content="{{ $beeFree['client_id'] ?? '' }}">
<meta name="bee-client-secret" content="{{ $beeFree['client_secret'] ?? '' }}">
