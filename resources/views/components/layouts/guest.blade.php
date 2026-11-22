@props(['title'])
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <x-layouts.head :title="$title" :forceLight="true" />
</head>
<body class="font-sans text-sm antialiased">
<main id="page-root">
    {{ $slot }}
</main>
<div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
