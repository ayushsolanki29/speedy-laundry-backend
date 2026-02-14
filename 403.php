<?php
http_response_code(403);
require_once __DIR__ . '/config/config.php';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden · <?= htmlspecialchars($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <style>
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
        @keyframes pulse-glow { 0%, 100% { opacity: 0.5; } 50% { opacity: 0.8; } }
        .lock-shake { animation: shake 0.5s ease-in-out; }
        .glow { animation: pulse-glow 2s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans">
    <div class="absolute inset-0 -z-10">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent"></div>
        <div class="absolute top-0 right-0 w-1/2 h-1/2 bg-red-500/10 rounded-full blur-[120px] glow"></div>
        <div class="absolute bottom-0 left-0 w-1/3 h-1/3 bg-primary/5 rounded-full blur-[100px]"></div>
    </div>

    <main class="flex-1 flex flex-col items-center justify-center px-4 py-16">
        <div class="max-w-lg w-full text-center">
            <!-- Lock icon -->
            <div class="w-24 h-24 mx-auto mb-6 rounded-2xl bg-red-500/20 border border-red-500/40 flex items-center justify-center lock-shake">
                <i class="fas fa-lock text-4xl text-red-400"></i>
            </div>
            <div class="text-7xl md:text-8xl font-black text-red-500/90 tracking-tighter mb-2">403</div>
            <div class="inline-flex items-center gap-2 bg-red-500/20 backdrop-blur-md px-4 py-2 rounded-full border border-red-500/30 mb-6">
                <i class="fas fa-ban text-red-400"></i>
                <span class="text-red-300 font-semibold">Access Forbidden</span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-white tracking-tight mb-3">
                Permission Denied
            </h1>
            <p class="text-white/60 text-sm md:text-base mb-8 max-w-md mx-auto">
                You don't have permission to access this resource. The page or folder is restricted.
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-medium hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200">
                <i class="fas fa-arrow-left"></i>
                Back to Backend
            </a>
            <p class="text-white/30 text-xs mt-8">
                <?= htmlspecialchars($siteName) ?> · Custom Error Page
            </p>
        </div>
    </main>

    <footer class="py-6 px-4 border-t border-white/10 text-center">
        <a href="https://ayushsolanki.site" target="_blank" rel="noopener noreferrer" class="text-white/50 text-xs hover:text-primary transition-colors">
            Made By Ayush Solanki
        </a>
    </footer>
</body>
</html>
