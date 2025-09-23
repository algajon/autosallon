@extends('layouts.app')
@section('title', 'KoreanCars.KS')
@section('content')
    <!-- Additional styles for index page -->
    <style>
        body {
            font-family: 'Bahnschrift', 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6, .heading {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.em;
            text-transform: uppercase;
        }
        
        p, .paragraph {
            font-family: 'Bahnschrift', 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
        }
        .hero-section {
            height: 100vh;
            background: linear-gradient(135deg, #0b0b0b 0%, #1a1a1a 100%);
        }
        .model-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .model-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .nav-link {
            position: relative;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: none;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #e10600;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .header-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        
        /* 3D Model Styles */
        #sketchfab-iframe {
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        .hero-section {
            transform-style: preserve-3d;
        }
        
        /* Custom scrollbar for better UX */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #0b0b0b;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #e10600;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #b10707;
        }
    </style>

    <!-- Additional scripts for index page -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Mobile Off-Canvas Menu -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>
    <aside id="mobile-menu" class="fixed inset-y-0 left-0 w-80 max-w-[85%] bg-brand-dark z-50 transform -translate-x-full transition-transform duration-300 ease-in-out md:hidden shadow-xl">
        <div class="relative h-full flex flex-col">
            <button id="mobile-menu-close" class="absolute top-3 right-3 p-2 text-gray-300 hover:text-white" aria-label="Mbyll menunë">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <div class="px-6 pt-6 pb-4 border-b border-gray-800">
                <span class="text-lg font-semibold header-logo text-white">KoreanCars.ks</span>
            </div>
            <nav class="flex-1 overflow-y-auto px-6 py-4 space-y-2">
                <a href="/search" class="block py-3 text-gray-300 hover:text-white">Modelet</a>
                <a href="#about" class="block py-3 text-gray-300 hover:text-white">Rreth Nesh</a>
                <a href="#support" class="block py-3 text-gray-300 hover:text-white">Mbështetje</a>
                <div class="pt-4 mt-2 border-t border-gray-800">
                    <a href="{{ route('login') }}" class="block py-3 font-medium text-white hover:text-brand-muted">Hyrje</a>
                    <a href="{{ route('register') }}" class="block py-3 font-medium text-white hover:text-brand-muted">Regjistrohu</a>
                </div>
            </nav>
        </div>
    </aside>

    <!-- Hero Section with 3D Model -->
    <section class="hero-section relative overflow-hidden">
        <!-- 3D Model Container -->
        <div id="sketchfab-container" class="absolute inset-0 w-full h-full">
            <iframe 
                title="Bentley Continental GT 2022" 
                frameborder="0" 
                allowfullscreen 
                mozallowfullscreen="true" 
                webkitallowfullscreen="true" 
                allow="autoplay; fullscreen; xr-spatial-tracking" 
                xr-spatial-tracking 
                execution-while-out-of-viewport 
                execution-while-not-rendered 
                web-share 
                src="https://sketchfab.com/models/cd1ba324a77d460aad92a3d683c9efec/embed?autostart=1&autospin=1"
                class="w-full h-full"
                id="sketchfab-iframe">
            </iframe>
        </div>
        
        <!-- Navigation Arrows -->
        <button id="prev-model" class="absolute left-4 top-1/2 transform -translate-y-1/2 z-30 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full transition-all duration-300 backdrop-blur-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>
        
        <button id="next-model" class="absolute right-4 top-1/2 transform -translate-y-1/2 z-30 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full transition-all duration-300 backdrop-blur-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
        
        <!-- Loading Indicator -->
        <div id="loading-indicator" class="absolute inset-0 bg-black/60 flex items-center justify-center z-30">
            <div class="text-center text-white">
                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-brand-red mx-auto mb-4"></div>
                <p class="text-lg">Duke ngarkuar modelin 3D...</p>
            </div>
        </div>
        
                    <!-- Floating Text Block -->
            <div class="absolute left-4 md:left-8 bottom-24 md:bottom-8 z-20 max-w-xs md:max-w-md">
                <div class="bg-black/70 backdrop-blur-sm p-4 md:p-8 rounded-lg border border-gray-800">
                    <h3 class="text-lg md:text-2xl font-bold text-white mb-2 md:mb-4 heading">KOREANCARS.KS</h3>
                    <p class="text-gray-300 paragraph text-sm md:text-base mb-2 md:text-base md:mb-4">
                        Zbuloni kulmin e inxhinierisë automobilistike. Koleksioni ynë përfaqëson mjeshtërinë më të mirë dhe teknologjinë më të avancuar.
                    </p>
                    <p class="text-gray-400 paragraph text-xs md:text-sm mb-4">
                        Përjetoni luksin e ridizajnuar me përzgjedhjen tonë të automjeteve premium.
                    </p>
                    <button class="bg-brand-red text-white px-6 py-2 rounded-lg font-semibold hover:bg-brand-red-dark transition-colors text-sm md:text-base">
                        BLEJ TANI
                    </button>
                </div>
            </div>
        
        <!-- Bottom Overlay to Hide Sketchfab Controls -->
        <div class="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-black via-black/90 to-transparent z-20"></div>
    </section>

    <!-- Models Section -->
<section id="models" class="py-16 bg-brand-black">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    @if(isset($instocks) && $instocks->count())
      <div class="text-center mb-8">
        <h2 class="text-4xl font-extrabold text-white tracking-tight">Makinat në Stok</h2>
        <div class="inline-flex items-center gap-2 mt-3 text-sm">
          <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-dark text-gray-200 border border-gray-800">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            Porosit tani!
          </span>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
        @foreach($instocks as $item)
          @php
            $img = $item->main_image_url ?? 'https://via.placeholder.com/640x360?text=Në+Stok';
            $instockPrice = isset($item->price) && $item->price !== null ? '€' . number_format((int)$item->price, 0, ',', '.') : '—';
          @endphp
          <div class="bg-brand-card border border-gray-800 rounded-2xl overflow-hidden shadow-sm flex flex-col hover:shadow-xl transition-shadow duration-200">
            <div class="relative w-full aspect-[4/3] bg-gradient-to-br from-gray-800 to-gray-700">
              <img src="{{ $img }}" alt="{{ $item->name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
              <div class="absolute top-3 left-3 px-2.5 py-1 text-[11px] font-semibold rounded-md bg-black/60 text-white border border-white/10">
                NË STOK
              </div>
            </div>
            <div class="p-4 flex flex-col gap-3 flex-1">
              <div>
                <h3 class="text-lg font-bold text-white leading-snug line-clamp-1">{{ $item->name }}</h3>
                @if($item->description)
                  <div class="text-sm text-white line-clamp-2">{{ $item->description }}</div>
                @endif
              </div>
              <div class="mt-auto flex items-center justify-between pt-1">
                <div class="text-white font-extrabold text-lg">{{ $instockPrice }}</div>
                <a href="https://wa.me/38348661161?text={{ urlencode('Përshëndetje! Jam i interesuar për ' . ($item->name ?? 'një automjet') . (($item->price ?? null) !== null ? (' (€' . number_format((int)$item->price, 0, ',', '.') . ')') : '') . '. A mund të më jepni më shumë informacion?') }}" target="_blank" rel="noopener" class="text-brand-red font-semibold hover:text-brand-red-dark">Kontakto Direkt</a>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <div class="text-center mb-8 text-gray-400">Aktualisht nuk ka makina në stok.</div>
    @endif

    <div class="text-center mb-8">
      <h2 class="text-4xl font-extrabold text-white tracking-tight">Makinat e Disponueshme</h2>
      <div class="inline-flex items-center gap-2 mt-3 text-sm">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-dark text-gray-200 border border-gray-800">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          Zgjedhja e Ditës · {{ $picksDateLabel ?? now()->format('d F') }}
        </span>
      </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      @forelse($vehicles as $v)
        @php
          // Normalize
          $id    = $v->id ?? null;
          $title = trim(($v->viti ? $v->viti.' ' : '').($v->prodhuesi ?? '').' '.($v->modeli ?? ''));
          $var   = $v->varianti ?? '';
          $km    = $v->kilometrazhi_km ? number_format((int)$v->kilometrazhi_km, 0, ',', '.').' km' : null;
          $tr    = $v->transmisioni ?: null;
          $fu    = $v->karburanti ?: null;
          $price = $v->cmimi_eur ? '€'.number_format((int)$v->cmimi_eur, 0, ',', '.') : '—';

          // Image (uses your accessor)
          $img = $v->main_image_url ?? 'https://via.placeholder.com/640x360?text=No+Image';
        @endphp

        <div class="bg-brand-card border border-gray-800 rounded-2xl overflow-hidden shadow-sm flex flex-col hover:shadow-xl transition-shadow duration-200">
          @if($id)
            <a href="{{ route('listing.show', $id) }}" class="block">
          @endif
              <div class="relative w-full aspect-[4/3] bg-gradient-to-br from-gray-800 to-gray-700">
                <img src="{{ $img }}" alt="{{ $title }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute top-3 left-3 px-2.5 py-1 text-[11px] font-semibold rounded-md bg-black/60 text-white border border-white/10">
                  {{ $v->vin ? substr($v->vin, -8) : 'ENCAR' }}
                </div>
              </div>
          @if($id)
            </a>
          @endif

          <div class="p-4 flex flex-col gap-3 flex-1">
            <div>
              <h3 class="text-lg font-bold text-white leading-snug line-clamp-1">{{ $title ?: 'Automjet' }}</h3>
              @if($var)
                <div class="text-sm text-brand-muted line-clamp-1">{{ $var }}</div>
              @endif
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
              @if($km) <span class="px-2.5 py-1 rounded-full bg-brand-dark text-gray-300 border border-gray-700">{{ $km }}</span> @endif
              @if($tr) <span class="px-2.5 py-1 rounded-full bg-brand-dark text-gray-300 border border-gray-700">{{ $tr }}</span> @endif
              @if($fu) <span class="px-2.5 py-1 rounded-full bg-brand-dark text-gray-300 border border-gray-700">{{ $fu }}</span> @endif
            </div>

            <div class="mt-auto flex items-center justify-between pt-1">
              <div class="text-white font-extrabold text-lg">{{ $price }}</div>
              @if($id)
                <a href="{{ route('listing.show', $id) }}" class="text-brand-red font-semibold hover:text-brand-red-dark">Shiko Detajet</a>
              @else
                <a class="text-brand-red font-semibold hover:text-brand-red-dark" href="https://www.instagram.com/direct/t/17842055736454790/" target="_blank" rel="noopener">Kontakto</a>
              @endif
            </div>

            <div class="text-[11px] text-green-400 border-t border-gray-800 pt-2">
              deri në portin e Durrësit!
            </div>
          </div>
        </div>
      @empty
        <div class="col-span-full text-center py-12">
          <div class="text-gray-400 text-lg mb-2">Nuk ka automjete për momentin</div>
          <p class="text-gray-500">Kthehuni së shpejti për zgjedhjet e reja të ditës.</p>
        </div>
      @endforelse
    </div>
  </div>
</section>

    <!-- Experience Section -->
    <section id="experience" class="py-20 bg-brand-dark">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Përvoja</h2>
                <p class="text-xl text-brand-muted">Zhytuni në botën e automjeteve premium</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-3xl font-bold text-white mb-6">Vizitoni Sallonin Tonë</h3>
                    <p class="text-lg text-brand-muted mb-8">
                        Përjetoni automjetet tona nga afër në sallonin tonë modern. 
                        Stafi ynë ekspert do t'ju udhëheqë në çdo detaj dhe do t'ju ndihmojë të gjeni 
                        automjetin e përkryer për stilin tuaj të jetesës.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-brand-red rounded-full flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-300">Konsultim personal me ekspertët</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-brand-red rounded-full flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-300">Mundësi për test-drejtim</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-brand-red rounded-full flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-300">Evente dhe paraqitje ekskluzive</span>
                        </div>
                    </div>
                    <button class="mt-8 bg-brand-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-brand-red-dark transition-colors">
                        Rezervoni Takim
                    </button>
                </div>
                <div class="h-96 bg-gradient-to-br from-gray-800 to-gray-700 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 text-lg">Imazh i Sallës</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Awards Section -->
    <section class="py-20 bg-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Çmime dhe Vlerësime</h2>
                <p class="text-xl text-brand-muted">Festojmë përsosmërinë në dizajn dhe inovacion automobilistik</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Award Card 1 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Përsosmëri në Dizajn</h3>
                    <p class="text-brand-muted">I vlerësuar për dizajn të jashtëzakonshëm dhe inovacion automobilistik</p>
                </div>

                <!-- Award Card 2 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Inovacion në Siguri</h3>
                    <p class="text-brand-muted">Vlerësime të larta sigurie dhe njohje për teknologji të avancuar sigurie</p>
                </div>

                <!-- Award Card 3 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Kënaqësi e Klientëve</h3>
                    <p class="text-brand-muted">Vlerësimet më të larta të kënaqësisë së klientëve në segmentin premium</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-brand-dark">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-white mb-6">Rreth KoreanCars</h2>
                    <p class="text-lg text-brand-muted mb-6">
                        Ne synojmë të krijojmë automjetet më të mira dhe produktet e shërbimet përkatëse 
                        për njohësit në mbarë botën. Angazhimi ynë për përsosmëri udhëheq çdo 
                        aspekt të procesit tonë të dizajnit dhe inxhinierisë.
                    </p>
                    <p class="text-lg text-brand-muted mb-8">
                        Nga koncepti te krijimi, besojmë se çdo drejtim meriton të jetë një zbulim. 
                        Automjetet tona mishërojnë ekuilibrin e përkryer midis luksit, performancës dhe inovacionit.
                    </p>
                    <button class="bg-brand-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-brand-red-dark transition-colors">
                        Mëso Më Shumë
                    </button>
                </div>
                <div class="h-96 bg-gradient-to-br from-gray-800 to-gray-700 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 text-lg">Imazh Informativ</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Section -->
    <section id="support" class="py-20 bg-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Mbështetje</h2>
                <p class="text-xl text-brand-muted">Ne ofrojmë shërbim të optimizuar për klientët që plotëson standardet e larta të tyre</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Support Card 1 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C15.802 8.249 16 9.1 16 10zm-5.165 3.913l1.58 1.58A5.98 5.98 0 0110 16a5.976 5.976 0 01-2.516-.552l1.562-1.562a4.006 4.006 0 001.789.027zm-4.677-2.796a4.002 4.002 0 01-.041-2.08l-.08.08-1.53-1.533A5.98 5.98 0 004 10c0 .954.223 1.856.619 2.657l1.54-1.54zm1.088-6.45A5.974 5.974 0 0110 4c.954 0 1.856.223 2.657.619l-1.54 1.54a4.002 4.002 0 00-2.346.033L7.246 4.668zM12 10a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Mbështetje 24/7</h3>
                    <p class="text-brand-muted">Mbështetje për klientët gjatë gjithë kohës për të gjitha nevojat tuaja</p>
                </div>

                <!-- Support Card 2 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Shërbim Garancie</h3>
                    <p class="text-brand-muted">Mbulesë e plotë garancie dhe plane shërbimi</p>
                </div>

                <!-- Support Card 3 -->
                <div class="bg-brand-card p-8 rounded-lg shadow-lg text-center border border-gray-800">
                    <div class="w-16 h-16 bg-brand-red rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Pjesë Këmbimi dhe Shërbim</h3>
                    <p class="text-brand-muted">Pjesë origjinale dhe teknikë shërbimi ekspertë</p>
                </div>
            </div>

            <div class="text-center mt-12">
                <a href="https://www.instagram.com/direct/t/17842055736454790/" class="bg-brand-red text-white px-8 py-3 rounded-lg font-semibold hover:bg-brand-red-dark transition-colors">
                    Kontakto Mbështetjen
    </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <script>
        // GSAP ScrollTrigger registration
        gsap.registerPlugin(ScrollTrigger);

        // 3D Model Loading and Autoplay
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.getElementById('sketchfab-iframe');
            const loadingIndicator = document.getElementById('loading-indicator');
            const prevButton = document.getElementById('prev-model');
            const nextButton = document.getElementById('next-model');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
            const mobileMenuOpen = document.getElementById('mobile-menu-open');
            const mobileMenuClose = document.getElementById('mobile-menu-close');

            // Model configurations
            const models = [
                {
                    title: "BMW M4",
                    src: "https://sketchfab.com/models/7bb75524ef6f4b45a684da6826e6b8ea/embed?autostart=1&autospin=1"
                },
                {
                    title: "Audi A-Class Model",
                    src: "https://sketchfab.com/models/29829c4cd0314e4bb0901cf7662209a9/embed?autostart=1&autospin=1"
                },
                {
                    title: "BMW 520i",
                    src: "https://sketchfab.com/models/b53782a00cc240a191d5d7d005af1423/embed?autostart=1&autospin=1"
                },
            ];
            
            let currentModelIndex = 0;

            // Function to load model
            function loadModel(index) {
                currentModelIndex = index;
                const model = models[index];
                
                // Show loading indicator
                loadingIndicator.style.display = 'flex';
                gsap.set(loadingIndicator, { opacity: 1 });
                
                // Update iframe
                iframe.title = model.title;
                iframe.src = model.src;
                
                // Hide loading indicator when iframe loads
                iframe.addEventListener('load', function() {
                    setTimeout(() => {
                        gsap.to(loadingIndicator, {
                            opacity: 0,
                            duration: 0.3,
                            onComplete: () => {
                                loadingIndicator.style.display = 'none';
                            }
                        });
                    }, 300);
                }, { once: true });
            }

            // Navigation event listeners
            prevButton.addEventListener('click', function() {
                const newIndex = currentModelIndex === 0 ? models.length - 1 : currentModelIndex - 1;
                loadModel(newIndex);
            });

            nextButton.addEventListener('click', function() {
                const newIndex = currentModelIndex === models.length - 1 ? 0 : currentModelIndex + 1;
                loadModel(newIndex);
            });

            // Initial load
            loadModel(0);

            // Mobile menu handlers
            function openMobileMenu() {
                mobileMenu.classList.remove('-translate-x-full');
                mobileMenuOverlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
            function closeMobileMenu() {
                mobileMenu.classList.add('-translate-x-full');
                mobileMenuOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            if (mobileMenuOpen) {
                mobileMenuOpen.addEventListener('click', openMobileMenu);
            }
            if (mobileMenuClose) {
                mobileMenuClose.addEventListener('click', closeMobileMenu);
            }
            if (mobileMenuOverlay) {
                mobileMenuOverlay.addEventListener('click', closeMobileMenu);
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeMobileMenu();
                }
            });
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Header is now statically black - no scroll effects needed
    </script>
@endsection