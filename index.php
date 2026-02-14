<?php
/**
 * Speedy Laundry Backend - Index
 * API entry point / info page
 */
$config = require __DIR__ . '/config/config.php';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?> · Backend</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0095da',
                        header: '#004787',
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen flex flex-col">
    <div class="absolute inset-0 -z-10">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent"></div>
        <div class="absolute top-0 right-0 w-1/2 h-1/2 bg-primary/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-0 left-0 w-1/3 h-1/3 bg-primary/5 rounded-full blur-[100px]"></div>
    </div>

    <main class="flex-1 flex flex-col items-center justify-center px-4 py-16">
        <div class="max-w-lg w-full text-center">
            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-full border border-white/10 mb-6">
                <span class="text-primary font-semibold">API Backend</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight mb-4">
                <?= htmlspecialchars($siteName) ?> <span class="text-primary">Backend</span>
            </h1>
            <p class="text-white/70 text-base mb-8">
                API endpoints are available at <code class="bg-white/10 px-2 py-1 rounded text-sm">/api/</code>
            </p>
            <div class="bg-white/10 backdrop-blur-xl rounded-2xl border border-white/10 p-6 text-left text-sm text-white/80">
                <p>This is the backend for Speedy Laundry. Use the admin panel to manage content.</p>
            </div>
        </div>
    </main>

    <footer class="py-6 px-4 border-t border-white/10 text-center">
        <a href="https://ayushsolanki.site" target="_blank" rel="noopener noreferrer" class="text-white/50 text-xs hover:text-primary transition-colors">
            Made By Ayush Solanki
        </a>
    </footer>
</body>
</html>
