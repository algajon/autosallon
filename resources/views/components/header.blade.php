<link rel="icon" href="/images/favicon.ico" sizes="any"> <!-- contains 16/32/48 -->
<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png"> <!-- 180x180 -->
<link rel="manifest" href="/site.webmanifest">             <!-- has 192 & 512 -->
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#d60000">
<meta name="theme-color" content="#000000">
<header class="fixed top-0 left-0 right-0 z-50 bg-black/95 backdrop-blur-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16 md:h-20">
      <!-- Logo -->
<div class="flex items-center -ml-4 md:ml-0 md:relative">
  <!-- NEW: desktop-only wordmark placed to the LEFT of the logo, no layout shift -->
  <span
    class="hidden md:block absolute right-full translate-x-3 top-1/2 -translate-y-1/2
           text-white text-[16px] lg:text-lg tracking-[0.2em] uppercase
           whitespace-nowrap select-none pointer-events-none">
    KOREANCARS.KS
  </span>

  <a href="{{ route('index') }}" class="flex items-center gap-2 mt-2" aria-label="KoreanCars">
    <img
      src="{{ asset('images/logoslick.png') }}"
      alt="KoreanCars logo"
      width="64" height="64"
      class="block h-32 mr-4 sm:-mr-4 w-auto md:h-10 lg:h-20 md:mr-3 md:mt-0
             shrink-0 object-contain select-none"
      fetchpriority="high" decoding="async" />
  </a>
</div>

      <!-- Center nav + desktop search -->
      <nav class="hidden mx-auto md:flex items-center space-x-6 lg:space-x-10">
        <a href="{{ route('vehicles.search') }}" class="nav-link text-white hover:text-brand-muted text-base lg:text-lg font-medium">
          Katalogu
        </a>
        <a href="{{ url('/#about') }}" class="nav-link text-white hover:text-brand-muted text-base lg:text-lg font-medium">
          Rreth Nesh
        </a>

        {{-- Desktop inline search --}}
        <form action="{{ route('vehicles.search') }}" method="GET"
              class="hidden lg:flex items-center w-[380px] bg-[#151515] border border-gray-800 rounded-full pl-4 pr-2 py-0.5">
<div id="desktop-suggest"
     class="hidden absolute mt-2 w-[420px] lg:w-[420px] z-50"></div>
              <input type="hidden" name="sort" value="relevance">
          <input type="text" name="q" value="{{ request('q') }}"
                 placeholder="Kërko makinën e ëndërrave!"
                 class="flex-1 bg-transparent text-sm text-gray-200 placeholder:text-gray-500 focus:outline-none">
          <button type="submit"
                  class="ml-2 rounded-full bg-brand-red hover:bg-brand-red-dark text-white px-3 py-1.5 text-sm font-semibold">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </button>
        </form>
      </nav>

      <!-- Right: auth + icons -->
      <div class="flex items-center gap-4">
        <!-- Mobile search trigger -->
        <button id="global-search-open" class="md:hidden p-2 text-white hover:text-brand-red transition-colors" aria-label="Kërko">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </button>
@auth
  <a href="{{ route('favorites.index') }}"
     class="nav-link group hidden md:inline-flex items-center mt-0.5 gap-2 relative">
    <svg viewBox="0 0 24 24"
         class="w-5 h-5 transition-transform duration-200 group-hover:-translate-y-1"
         fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
         style="filter: drop-shadow(0 0 6px rgba(239,68,68,.35));">
      <path d="M20.8 4.6a5.5 5.5 0 0 0-7.78 0L12 5.62l-1.02-1.02a5.5 5.5 0 1 0-7.78 7.78L12 21l8.8-8.62a5.5 5.5 0 0 0 0-7.78z"/>
    </svg>
    <span>Të preferuarat</span>
  </a>
@endauth
        <!-- Auth -->
        <div class="hidden md:flex items-center space-x-6">
          @auth
            @php
              $email = auth()->user()->email ?? '';
              $local = $email ? explode('@', $email)[0] : '';
              $username = $local ? preg_replace('/[^A-Za-z0-9]+/', '', $local) : '';
            @endphp
            @if(auth()->user()->isAdmin())
              <a href="{{ route('admin.vehicles.index') }}" class="nav-link text-white text-base lg:text-lg font-medium hover:text-brand-red transition-colors">{{ $username ?: 'Admin' }}</a>
            @else
              <span class="nav-link text-white text-base lg:text-lg font-medium">{{ $username ?: 'User' }}</span>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="inline">
              @csrf
              <button type="submit" class="nav-link text-white text-base lg:text-lg font-medium hover:text-brand-red transition-colors">
                Logout
              </button>
            </form>
          @else
            <a href="{{ route('login') }}" class="nav-link text-white text-base lg:text-lg font-medium hover:text-brand-red transition-colors">Hyr</a>
            <a href="{{ route('register') }}" class="nav-link text-white text-base lg:text-lg font-medium hover:text-brand-red transition-colors">Regjistrohu</a>
          @endauth
        </div>

        <!-- Mobile menu -->
        <button id="mobile-menu-open" class="md:hidden p-2 text-white hover:text-gray-300" aria-label="Hap menunë">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Mobile search overlay -->
  <div id="global-search-overlay"
       class="md:hidden fixed inset-0 bg-black/60 backdrop-blur-sm hidden"></div>

  <!-- Mobile slide-down search -->
  <div id="global-search-panel"
       class="md:hidden border-t border-gray-800 bg-[#0b0b0b] hidden transform -translate-y-2 opacity-0 transition-all duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
      <form action="{{ route('vehicles.search') }}" method="GET" class="flex items-center gap-2">
      <div id="mobile-suggest" class="hidden mt-2"></div>  
      <input type="hidden" name="sort" value="relevance">
        <input id="global-search-input" type="text" name="q" placeholder="Kërko katalogun…"
               class="flex-1 bg-[#151515] text-gray-200 placeholder:text-gray-500 border border-gray-800 rounded-lg px-3 py-2 focus:outline-none">
        <button type="submit" class="bg-brand-red hover:bg-brand-red-dark text-white font-semibold rounded-lg px-4 py-2">
          Kërko
        </button>
        <button type="button" id="global-search-close"
                class="px-3 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">
          Mbyll
        </button>
      </form>

      <!-- Quick chips -->
      <div class="mt-2 flex flex-wrap gap-2">
        <a href="{{ route('vehicles.search', ['q'=>'BMW']) }}" class="text-xs px-3 py-1 rounded-full bg-gray-800 text-gray-200 border border-gray-700">BMW</a>
        <a href="{{ route('vehicles.search', ['q'=>'Mercedes']) }}" class="text-xs px-3 py-1 rounded-full bg-gray-800 text-gray-200 border border-gray-700">Mercedes</a>
        <a href="{{ route('vehicles.search', ['q'=>'Audi']) }}" class="text-xs px-3 py-1 rounded-full bg-gray-800 text-gray-200 border border-gray-700">Audi</a>
      </div>
    </div>
  </div>
</header>

<style>
  .group:hover svg { filter: drop-shadow(0 0 12px rgba(239,68,68,.55)); }
</style>

{{-- Lightweight behavior --}}
<script>
  (function () {
  // Elements
  const desktopForm  = document.querySelector('nav form[action*="vehicles/search"]');
  const desktopInput = desktopForm ? desktopForm.querySelector('input[name="q"]') : null;
  const desktopDrop  = document.getElementById('desktop-suggest');

  const mobileInput  = document.getElementById('global-search-input');
  const mobileDrop   = document.getElementById('mobile-suggest');

  // Helper to attach live search to any input + panel
  function attachLive(input, panel) {
    if (!input || !panel) return;

    // Make sure the panel is positioned relative to something
    // For desktop, we use absolute; for mobile it's static (block).
    if (panel === desktopDrop) {
      const wrapper = input.closest('form');
      if (wrapper && getComputedStyle(wrapper).position === 'static') {
        wrapper.style.position = 'relative';
      }
    }

    let t, lastQ = '';
    async function run(q) {
      lastQ = q;
      if (!q.trim()) { panel.classList.add('hidden'); panel.innerHTML = ''; return; }
      const url = `{{ route('vehicles.suggest') }}?q=${encodeURIComponent(q)}`;
      try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        const html = await res.text();
        // Only render if it's still the latest query
        if (q !== lastQ) return;
        panel.innerHTML = html.trim();
        if (html.trim()) panel.classList.remove('hidden'); else panel.classList.add('hidden');
      } catch (_) {
        panel.classList.add('hidden');
      }
    }

    input.addEventListener('input', () => {
      clearTimeout(t);
      const q = input.value || '';
      t = setTimeout(() => run(q), 200);
    });

    // Hide on outside click / Esc
    document.addEventListener('click', (e) => {
      if (!panel.contains(e.target) && e.target !== input) {
        panel.classList.add('hidden');
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') panel.classList.add('hidden');
    });
  }

  attachLive(desktopInput, desktopDrop);
  attachLive(mobileInput,  mobileDrop);
})();
  (function () {
    const openBtn   = document.getElementById('global-search-open');
    const closeBtn  = document.getElementById('global-search-close');
    const panel     = document.getElementById('global-search-panel');
    const overlay   = document.getElementById('global-search-overlay');
    const input     = document.getElementById('global-search-input');

    function openPanel() {
      panel.classList.remove('hidden');
      overlay.classList.remove('hidden');
      requestAnimationFrame(() => {
        panel.classList.remove('-translate-y-2', 'opacity-0');
        panel.classList.add('translate-y-0', 'opacity-100');
      });
      setTimeout(() => input && input.focus(), 80);
      // close mobile menu if open (optional; depends on your code)
      const mobileMenu = document.getElementById('mobile-menu');
      const mobileOverlay = document.getElementById('mobile-menu-overlay');
      if (mobileMenu && !mobileMenu.classList.contains('-translate-x-full')) {
        mobileMenu.classList.add('-translate-x-full');
        mobileOverlay && mobileOverlay.classList.add('hidden');
      }
    }

    function closePanel() {
      panel.classList.add('-translate-y-2', 'opacity-0');
      panel.classList.remove('translate-y-0', 'opacity-100');
      setTimeout(() => {
        panel.classList.add('hidden');
        overlay.classList.add('hidden');
      }, 180);
    }

    if (openBtn)  openBtn.addEventListener('click', openPanel);
    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    if (overlay)  overlay.addEventListener('click', closePanel);

    // ESC to close
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !panel.classList.contains('hidden')) closePanel();
      // Shortcut "/" to open
      if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) {
        const tag = (e.target.tagName || '').toLowerCase();
        if (!['input','textarea','select'].includes(tag)) {
          e.preventDefault();
          openPanel();
        }
      }
    });
  })();

  // Smooth scroll for "Rreth Nesh" link
  (function () {
    function smoothScrollToAbout(e) {
      const target = document.getElementById('about');
      if (!target) return false;
      e.preventDefault();
      const headerEl = document.querySelector('header');
      const offset = headerEl ? headerEl.getBoundingClientRect().height : 0;
      const top = target.getBoundingClientRect().top + window.pageYOffset - offset - 8;
      window.scrollTo({ top, behavior: 'smooth' });
      return true;
    }

    // Intercept clicks on any link that points to #about
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[href$="#about"]');
      if (!a) return;
      try {
        const url = new URL(a.href, window.location.origin);
        // If link points to the current page (same path), smooth scroll; otherwise allow navigation
        if (url.pathname === window.location.pathname) {
          smoothScrollToAbout(e);
        }
      } catch (_) {
        // Fallback for unusual hrefs
        if (a.getAttribute('href') === '#about') {
          smoothScrollToAbout(e);
        }
      }
    });
  })();
</script>
