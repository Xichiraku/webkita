<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
    <meta name="midtrans-production" content="{{ config('services.midtrans.is_production') ? 1 : 0 }}">
    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="user-staff" content="{{ auth()->user()->isStaff() ? 1 : 0 }}">
    @endauth
    <title>{{ isset($title) ? $title.' · ' : '' }}Webkita</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <script>document.documentElement.classList.add('js')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="{{ $bodyClass ?? '' }}">
    {{ $slot }}
    <x-toast />
    {{ $scripts ?? '' }}
</body>
</html>
