<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIM Absensi RS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#0f172a', accent: '#0ea5e9' }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-50 font-sans antialiased flex items-center justify-center min-h-screen relative overflow-hidden">

    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-accent/20 rounded-full blur-3xl"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-blue-600/10 rounded-full blur-3xl"></div>

    <div
        class="w-full max-w-md bg-white/80 backdrop-blur-xl p-8 sm:p-10 rounded-3xl shadow-2xl border border-white relative z-10">
        @php
            $pengaturanGlobal = \App\Models\PengaturanAplikasi::first();
        @endphp

        <div class="flex justify-center mb-5">
            @if($pengaturanGlobal && $pengaturanGlobal->logo)
                <img src="{{ asset('storage/' . $pengaturanGlobal->logo) }}" alt="Logo" class="h-20 w-auto object-contain drop-shadow-md">
            @else
                <div class="w-16 h-16 bg-gradient-to-tr from-accent to-blue-500 rounded-2xl flex items-center justify-center shadow-lg shadow-accent/30 text-white">
                    <i data-lucide="activity" class="w-8 h-8"></i>
                </div>
            @endif
        </div>

        <div class="text-center mb-8">
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight mb-2">
                {{ $pengaturanGlobal->nama_instansi ?? 'SIM Absensi RS' }}
            </h2>
            <p class="text-sm font-medium text-slate-500">
                Silakan masuk menggunakan Username atau NIK Anda
            </p>
        </div>

        @if ($errors->any())
        <div
            class="bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl text-sm mb-6 flex gap-3 items-start">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
            <span class="font-medium">{{ $errors->first() }}</span>
        </div>
        @endif

        <form action="/login" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Username atau NIK</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <input type="text" name="login" required
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-semibold text-slate-800 placeholder-slate-400"
                        placeholder="Contoh: luthfi_admin atau 1001001" value="{{ old('login') }}">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Kata Sandi</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                    </div>
                    <input type="password" name="password" required
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-semibold text-slate-800 placeholder-slate-400"
                        placeholder="••••••••">
                </div>
            </div>

            <button type="submit"
                class="w-full bg-primary hover:bg-slate-800 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-primary/20 transition-all flex justify-center items-center gap-2 mt-6">
                Masuk ke Dashboard <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>