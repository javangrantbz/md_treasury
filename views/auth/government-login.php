<?php
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/MicrosoftAuth.php';
if (!empty($_SESSION['user'])) {
    redirect(url('/views/portal/index.php'));
}
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$microsoftLoginUrl = MicrosoftAuth::getLoginUrl(abs_url('views/auth/microsoft-callback.php'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Treasury Revenue System — Government of Belize</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'] },
          colors: {
            navy: { 700: '#1a3a5c', 800: '#0f2a45', 900: '#0a1f33' }
          }
        }
      }
    }
  </script>
  <style>
    html, body { height: 100%; }

    .mesh-right {
      background-color: #e4ecf6;
      background-image:
        radial-gradient(at 20% 20%, rgba(59,130,246,.13) 0px, transparent 55%),
        radial-gradient(at 80% 15%, rgba(139,92,246,.09) 0px, transparent 50%),
        radial-gradient(at 50% 80%, rgba(16,185,129,.08) 0px, transparent 50%),
        radial-gradient(at 90% 75%, rgba(10,38,71,.07) 0px, transparent 45%);
    }

    .glass-card {
      background: rgba(255,255,255,.65);
      backdrop-filter: blur(32px) saturate(180%);
      -webkit-backdrop-filter: blur(32px) saturate(180%);
      border: 1px solid rgba(255,255,255,.55);
      box-shadow: 0 0 0 .5px rgba(255,255,255,.7) inset,
                  0 8px 36px rgba(0,0,0,.10),
                  0 2px 8px rgba(0,0,0,.05);
    }

    .glass-input {
      background: rgba(255,255,255,.60);
      border: 1px solid rgba(180,200,220,.70);
      transition: all .22s ease;
    }
    .glass-input:focus {
      background: rgba(255,255,255,.88);
      border-color: rgba(26,58,92,.40);
      box-shadow: 0 0 0 3px rgba(26,58,92,.09);
      outline: none;
    }
    .glass-input.is-invalid { border-color: #ef4444; }

    .btn-signin {
      background: linear-gradient(135deg, #1f4d80 0%, #0f2a45 100%);
      box-shadow: 0 4px 14px rgba(15,42,69,.32), 0 1px 3px rgba(0,0,0,.12);
      transition: all .22s ease;
    }
    .btn-signin:hover {
      background: linear-gradient(135deg, #255a92 0%, #122f4e 100%);
      box-shadow: 0 6px 20px rgba(15,42,69,.40), 0 2px 5px rgba(0,0,0,.14);
      transform: translateY(-1px);
    }
    .btn-signin:active { transform: translateY(0); }

    .brand-panel-bg {
      background: linear-gradient(165deg, #1b3d64 0%, #122d4a 45%, #0c2038 100%);
    }
    .brand-panel-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(ellipse at 10% 90%, rgba(255,255,255,.06) 0%, transparent 50%),
        radial-gradient(ellipse at 90% 10%, rgba(255,255,255,.04) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 50%, rgba(30,70,120,.25) 0%, transparent 70%);
      pointer-events: none;
    }
    .brand-panel-bg::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='1' cy='1' r='1' fill='rgba(255,255,255,0.028)'/%3E%3C/svg%3E");
      background-size: 40px 40px;
      pointer-events: none;
    }


    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fade-up   { animation: fadeInUp .5s cubic-bezier(.25,.46,.45,.94) both; }
    .delay-1   { animation-delay: .07s; }
    .delay-2   { animation-delay: .14s; }
    .delay-3   { animation-delay: .21s; }
    .delay-4   { animation-delay: .28s; }

    .caps-warning { display: none; }
    .caps-warning.show { display: flex; }
  </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

  <!-- Government top bar -->
  <div class="bg-navy-900 text-white/60 text-[.68rem] font-semibold tracking-[.1em] uppercase px-5 py-2 flex items-center gap-2 flex-shrink-0">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </svg>
    Government of Belize &nbsp;&bull;&nbsp; Ministry of Finance &nbsp;&bull;&nbsp; Restricted Government System
  </div>

  <!-- Split layout -->
  <div class="flex flex-1 min-h-0">

    <!-- LEFT: Branding panel -->
    <div class="brand-panel-bg relative hidden md:flex md:w-[38%] lg:w-[40%] flex-col p-9 xl:p-12">
      <div class="relative flex flex-col h-full">

        <!-- TOP: Country identity -->
        <div class="flex items-center gap-2 mb-auto">
          <span class="text-[.58rem] font-semibold tracking-[.22em] uppercase text-white/55">
            Government of Belize
          </span>
        </div>

        <!-- CENTER: Institutional identity — vertically dominant -->
        <div class="flex flex-col items-center text-center my-auto pt-2 pb-6">

          <img src="<?= url('assets/img/coat-of-arms.png') ?>"
               alt="Coat of Arms of Belize"
               class="h-24 w-auto mb-6 drop-shadow-[0_6px_24px_rgba(0,0,0,.6)]"
               style="image-rendering: -webkit-optimize-contrast;">

          <div class="text-[.65rem] font-semibold tracking-[.2em] uppercase text-white/40 mb-2">
            Ministry of Finance
          </div>
          <div class="text-[.58rem] tracking-[.16em] uppercase text-white/22 mb-7">
            Treasury Department
          </div>

          <div class="w-6 h-px bg-white/20 mb-7"></div>

          <h1 class="text-[2rem] font-bold text-white tracking-tight leading-none mb-2.5">
            Treasury Revenue System
          </h1>
          <p class="text-[.72rem] text-white/35 tracking-[.08em] uppercase font-light mb-5">
            Integrated National Financial System
          </p>

          <!-- System status -->
          <div class="flex items-center gap-2 mb-7">
            <span class="relative flex h-1.5 w-1.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-50"></span>
              <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400/80"></span>
            </span>
            <span class="text-[.60rem] font-medium tracking-[.14em] uppercase text-white/35">
              System Status&nbsp;·&nbsp;Operational
            </span>
          </div>

          <!-- Supporting items -->
          <div class="w-full border-t border-white/[.08] pt-5 space-y-3 text-left">
            <div class="flex items-center gap-3">
              <div class="w-[2px] h-3.5 rounded-full bg-white/20 flex-shrink-0"></div>
              <span class="text-[.76rem] text-white/45 tracking-wide">Centralised treasury operations</span>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-[2px] h-3.5 rounded-full bg-white/20 flex-shrink-0"></div>
              <span class="text-[.76rem] text-white/45 tracking-wide">Real-time financial visibility</span>
            </div>
          </div>

        </div>

        <!-- BOTTOM: Classification notice -->
        <div class="mt-auto pt-5 border-t border-white/[.08]">
          <div class="flex items-start gap-2.5">
            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-white/25" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <p class="text-[.64rem] text-white/25 leading-relaxed tracking-wide">
              Restricted government system. Authorised users only.<br>
              All sessions are encrypted, monitored, and audited.
            </p>
          </div>
        </div>

      </div>
    </div>

    <!-- RIGHT: Form panel -->
    <div class="flex-1 mesh-right flex flex-col items-center justify-center p-6 overflow-y-auto">
      <div class="w-full max-w-sm my-10">

        <!-- Mobile: compact brand header -->
        <div class="flex md:hidden items-center gap-3 mb-6 fade-up">
          <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Coat of Arms" class="h-10 w-auto">
          <div>
            <div class="text-[.62rem] font-semibold tracking-widest uppercase text-gray-400">Government of Belize</div>
            <div class="text-base font-bold text-gray-800">Treasury Revenue System</div>
          </div>
        </div>

        <!-- Card -->
        <div class="glass-card rounded-3xl overflow-hidden fade-up delay-1">

          <!-- Accent bar -->
          <div class="h-[4px] bg-gradient-to-r from-navy-800 via-gold-400 to-navy-800"></div>

          <div class="p-7 sm:p-8">

            <h2 class="text-lg font-bold text-gray-900 mb-0.5">Sign In</h2>
            <p class="text-xs text-gray-500 mb-5">Enter your credentials to access Treasury Revenue System.</p>

            <!-- Security notice -->
            <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-3 mb-5 fade-up delay-2">
              <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
              <span class="text-[.76rem] text-amber-900 leading-snug">
                <strong class="font-bold">Authorised Access Only.</strong><br>
                Unauthorised use of this system is strictly prohibited and subject to legal action. All activity is logged and audited.
              </span>
            </div>

            <!-- Error alert -->
            <?php if ($error): ?>
            <div class="flex items-center gap-2.5 bg-red-50 border border-red-200 rounded-xl px-3.5 py-3 mb-5 fade-up" role="alert">
              <svg class="w-4 h-4 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span class="text-xs text-red-800 font-semibold"><?= h($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= url('api/auth/login.php') ?>" autocomplete="on" id="login-form" class="space-y-4">

              <!-- Email -->
              <div class="fade-up delay-2">
                <label for="login" class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                  Government Email
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                    </svg>
                  </div>
                  <input type="email" id="login" name="login" required autocomplete="email" autofocus
                    placeholder="you@gobmail.gov.bz" aria-required="true"
                    class="glass-input <?= $error ? 'is-invalid' : '' ?> w-full pl-10 pr-4 py-3 rounded-xl text-sm text-gray-900 placeholder-gray-400">
                </div>
              </div>

              <!-- Password -->
              <div class="fade-up delay-3">
                <label for="password" class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                  Password
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                  </div>
                  <input type="password" id="password" name="password" required autocomplete="current-password"
                    placeholder="••••••••" aria-required="true"
                    class="glass-input <?= $error ? 'is-invalid' : '' ?> w-full pl-10 pr-10 py-3 rounded-xl text-sm text-gray-900 placeholder-gray-400">
                  <button type="button" id="pw-toggle" tabindex="-1" aria-label="Show password"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                    <svg id="icon-eye" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                    <svg id="icon-eye-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                    </svg>
                  </button>
                </div>
                <!-- Caps lock warning -->
                <div class="caps-warning items-center gap-1.5 text-[.72rem] text-amber-700 font-medium mt-1.5" id="caps-warning">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                  </svg>
                  Caps Lock is active
                </div>
              </div>

              <!-- Submit -->
              <div class="pt-1 fade-up delay-4">
                <button type="submit"
                  class="btn-signin w-full py-3 rounded-xl text-white text-sm font-bold tracking-wide flex items-center justify-center gap-2.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                  Sign In to System
                </button>
              </div>

            </form>

            <!-- Divider -->
            <div class="flex items-center gap-3 mt-5 fade-up delay-4">
              <div class="flex-1 h-px bg-gray-200/80"></div>
              <span class="text-[.68rem] text-gray-400 font-medium tracking-wide">or continue with</span>
              <div class="flex-1 h-px bg-gray-200/80"></div>
            </div>

            <!-- Microsoft SSO -->
            <div class="mt-4 fade-up delay-4">
              <a href="<?= h($microsoftLoginUrl) ?>"
                 class="flex items-center justify-center gap-2.5 w-full py-3 px-4 rounded-xl border border-gray-300/80 bg-white/70 hover:bg-white hover:border-gray-400 text-sm font-semibold text-gray-700 tracking-wide transition-all duration-200 shadow-sm hover:shadow-md group">
                <svg class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                  <rect x="1"  y="1"  width="9" height="9" fill="#F25022"/>
                  <rect x="11" y="1"  width="9" height="9" fill="#7FBA00"/>
                  <rect x="1"  y="11" width="9" height="9" fill="#00A4EF"/>
                  <rect x="11" y="11" width="9" height="9" fill="#FFB900"/>
                </svg>
                Sign in with Microsoft
              </a>
              <p class="text-center text-[.68rem] text-gray-400 mt-2">For government staff with a GOB Microsoft account</p>
            </div>

          </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-[11px] text-gray-400 mt-5 leading-relaxed fade-up delay-4">
          For access issues, contact the IT Helpdesk<br>
          <svg class="inline w-3 h-3 mr-0.5 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          All sessions are encrypted and audited
        </p>

      </div>
    </div>
  </div>

  <script>
    // Password show/hide toggle
    const pwField    = document.getElementById('password');
    const pwToggle   = document.getElementById('pw-toggle');
    const iconEye    = document.getElementById('icon-eye');
    const iconEyeOff = document.getElementById('icon-eye-off');

    pwToggle.addEventListener('click', () => {
      const show = pwField.type === 'password';
      pwField.type = show ? 'text' : 'password';
      iconEye.classList.toggle('hidden', show);
      iconEyeOff.classList.toggle('hidden', !show);
    });

    // Caps lock detection
    const capsWarning = document.getElementById('caps-warning');
    pwField.addEventListener('keydown', e => capsWarning.classList.toggle('show', e.getModifierState('CapsLock')));
    pwField.addEventListener('keyup',   e => capsWarning.classList.toggle('show', e.getModifierState('CapsLock')));
  </script>

</body>
</html>
