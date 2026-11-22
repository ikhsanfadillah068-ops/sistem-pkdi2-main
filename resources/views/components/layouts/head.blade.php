@props(['title', 'forceLight' => false])
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/png" href="{{ asset('Assets/img/logo.png') }}">
<title>{{ $title }} - {{ config('pkdi.name') }}</title>
@if ($forceLight)
<script>
    document.documentElement.dataset.theme = 'light';
</script>
@else
<script>
    (function () {
        try {
            var root = document.documentElement;
            var theme = localStorage.getItem('theme');
            if (theme === 'light' || theme === 'dark') root.dataset.theme = theme;
            if (localStorage.getItem('sbmode') === 'mini') root.dataset.sbmode = 'mini';
        } catch (error) {}
    })();
</script>
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
