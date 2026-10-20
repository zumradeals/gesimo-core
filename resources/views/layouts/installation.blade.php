<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0f766e">
    <title>{{ isset($title) ? $title.' — ' : '' }}Installation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="flex h-14 items-center bg-gradient-to-r from-marque-600 to-marque-500 px-4 text-white lg:px-10">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 font-extrabold">G</span>
        <span class="ml-2 text-lg font-extrabold">Installation</span>
    </header>
    <main class="mx-auto w-full max-w-3xl px-4 py-6 lg:py-10">
        {{ $slot }}
    </main>
</body>
</html>
