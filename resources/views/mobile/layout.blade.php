<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Apple iOS Safari PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SIRO Mobile">
    <meta name="theme-color" content="#10B981">
    <meta name="format-detection" content="telephone=no">

    <link rel="manifest" href="{{ route('app.manifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.ico') }}">

    <title>@yield('title', 'SIRO Mobile')</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN dengan Palette Emerald Identik Flutter SIROM) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            DEFAULT: '#10B981',
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        surface: '#FFFFFF',
                        background: '#F8FAFC',
                    },
                    boxShadow: {
                        'float': '0 10px 25px -5px rgba(16, 185, 129, 0.2), 0 8px 10px -6px rgba(16, 185, 129, 0.2)',
                        'bottom-bar': '0 -4px 20px -2px rgba(0, 0, 0, 0.06)',
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js & Lucide Icons -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* iOS Safe Area Padding */
        :root {
            --sat: env(safe-area-inset-top, 0px);
            --sab: env(safe-area-inset-bottom, 0px);
            --sal: env(safe-area-inset-left, 0px);
            --sar: env(safe-area-inset-right, 0px);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            user-select: none;
            padding-top: var(--sat);
            padding-bottom: var(--sab);
        }

        /* Hilangkan Scrollbar default tapi tetap bisa scroll */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Animasi Shimmer Loading */
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
        .animate-shimmer {
            position: relative;
            overflow: hidden;
        }
        .animate-shimmer::after {
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            transform: translateX(-100%);
            background-image: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0,
                rgba(255, 255, 255, 0.4) 20%,
                rgba(255, 255, 255, 0.7) 60%,
                rgba(255, 255, 255, 0)
            );
            animation: shimmer 1.5s infinite;
            content: '';
        }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased flex flex-col justify-between">

    <!-- Mobile Screen Frame (Maksimal Lebar Layar Mobile / Responsive) -->
    <div class="w-full max-w-md mx-auto min-h-screen bg-background flex flex-col relative shadow-xl overflow-x-hidden">
        
        @yield('content')

    </div>

    <!-- Inisialisasi Lucide Icon di setiap render -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                lucide.createIcons();
            }
        });

        // Re-run Lucide saat Alpine merender ulang komponen
        document.addEventListener('alpine:initialized', () => {
            if (window.lucide) {
                setTimeout(() => lucide.createIcons(), 50);
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
