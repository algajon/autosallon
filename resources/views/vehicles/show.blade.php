@extends('layouts.app')
@section('title', $title ?? ($vehicle->viti.' '.$vehicle->prodhuesi.' '.$vehicle->modeli))
@section('content')
<style>
  /* --- Header alignment fix (this page only) --- */
  header .h-16, header .md\:h-20 { line-height: 1; }
  header nav .nav-link{
    display:flex; align-items:center; height:42px; padding-top:0; padding-bottom:0; line-height:1;
  }
  header nav form[action*="vehicles/search"]{
    display:flex; align-items:center; gap:.5rem;
    height:42px; padding:0 .5rem 0 1rem; border-radius:9999px; position:relative; top:1px; /* tiny nudge */
  }
  header nav form[action*="vehicles/search"] input[name="q"]{ height:100%; padding:0; margin:0; line-height:1; }
  header nav form[action*="vehicles/search"] button[type="submit"]{
    height:34px; display:inline-flex; align-items:center; justify-content:center; padding:0 .75rem; border-radius:9999px;
  }
  header nav form[action*="vehicles/search"] svg{ width:20px; height:20px; }

  /* --- Gallery --- */
  .gallery-main{
    background:#0f1115;border-radius:14px;overflow:hidden;
    aspect-ratio:16/9;max-height:56vh;
  }
  .thumbs{display:flex;gap:.5rem;overflow-x:auto;padding:.5rem 0}
  .thumbs img{width:68px;height:68px;object-fit:cover;border-radius:10px;opacity:.95;transition:.15s}
  .thumbs img:hover{opacity:1;transform:translateY(-1px)}
  /* hide native scrollbars (thumbs) */
  .thumbs{scrollbar-width:none;-ms-overflow-style:none}
  .thumbs::-webkit-scrollbar{display:none;height:0}

  .spec-card{background:#121622;border:1px solid #262c3f;border-radius:14px}
  .contact-card{background:#0f1422;border:1px solid #262c3f;border-radius:14px}
  .spec-tiles>div{min-height:82px;display:flex;flex-direction:column;justify-content:center}

  /* --- Lightbox (with zoom + pan) --- */
  .lb{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:70;display:none}
  .lb.show{display:flex}
  .lb-inner{width:100%;max-width:1400px;margin:auto;padding:1rem;display:flex;flex-direction:column;gap:.75rem}
  .lb-stage{position:relative;height:78vh;background:#000;border-radius:14px;overflow:hidden;cursor:grab}
  .lb-stage.grabbing{cursor:grabbing}
  .lb-img{
    position:absolute;top:50%;left:50%;
    transform:translate(-50%,-50%) scale(1);
    user-select:none;-webkit-user-drag:none;max-width:none;
    width:auto;height:auto; 
    transform-origin:center center;
    background:#000;
  }
  .lb-btn{position:absolute;top:50%;transform:translateY(-50%);width:46px;height:46px;border-radius:12px;
          display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;
          background:rgba(20,20,20,.6);border:1px solid rgba(255,255,255,.15)}
  .lb-prev{left:12px}
  .lb-next{right:12px}
  .lb-close{top:12px;right:12px;transform:none}
  .lb-strip{display:flex;gap:.5rem;overflow-x:auto}
  .lb-strip img{width:84px;height:84px;object-fit:cover;border-radius:8px;opacity:.85}
  .lb-strip img.active{outline:2px solid #ef4444;opacity:1}
  .lb-strip{scrollbar-width:none}
  .lb-strip::-webkit-scrollbar{display:none}
</style>

@php
  // Hi-res proxy (WebP + DPR)
  $hi = function (?string $url, int $w = 1920, int $dpr = 1) {
      if (!$url) return $url;
      return 'https://wsrv.nl/?url='.urlencode($url).'&w='.$w.'&dpr='.$dpr.'&output=webp&q=85&n=-1';
  };

  $images = $images ?? [];
  if (!$images && !empty($vehicle->images)) {
      $images = is_array($vehicle->images)
        ? array_values(array_filter($vehicle->images))
        : array_values(array_filter(array_map('trim', explode(';', (string)$vehicle->images))));
  }

  $imagesHi = array_map(fn($u) => [
      'x1' => $hi($u, 1920, 1),
      'x2' => $hi($u, 1920, 2),
      'orig' => $u,
  ], $images);
  $titleLine = $title ?? trim(($vehicle->viti ? $vehicle->viti.' ' : '')
                .($vehicle->prodhuesi ?? $vehicle->manufacturer ?? '')
                .' '
                .($vehicle->modeli ?? $vehicle->model ?? ''));

  $priceNum  = isset($vehicle->cmimi_eur) ? (int)$vehicle->cmimi_eur : null;
  $priceLine = $priceNum ? ' (€'.number_format($priceNum,0,',','.').')' : '';

  $waText = "Përshëndetje! Jam i interesuar për {$titleLine}{$priceLine}. A mund të më jepni më shumë informacion?";
  // lightbox uses sharper 2x
  $imagesLB = array_values(array_map(
      fn($i) => $i['orig'] ?: ($i['x2'] ?? $i['x1']),
      $imagesHi
  ));
  $mainImage = $mainImage ?? ($images[0] ?? 'https://via.placeholder.com/1600x900?text=No+Image');
  $mainX1 = $hi($mainImage, 2200, 1);
  $mainX2 = $hi($mainImage, 2200, 2);

  $title = $vehicle->display_title
    ?? trim(($vehicle->viti ? $vehicle->viti.' ' : '')
         . ($vehicle->prodhuesi ?? $vehicle->manufacturer ?? '')
         . ' '
         . ($vehicle->modeli ?? $vehicle->model ?? ''));

  $reportUrl = $reportUrl
    ?? (function($v){
          $raw = (string)($v->raporti_url ?? '');
          if (!$raw) return null;
          $parts = array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', $raw))));
          return $parts[0] ?? null;
       })($vehicle);

  $options = $options
    ?? (function($v){
          $raw = (string)($v->opsionet ?? '');
          if (!$raw) return [];
          $parts = array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', $raw))));
          return array_slice($parts, 0, 24);
       })($vehicle);

  // Option cleaning/localization — fully Albanian, remove negatives
  $optMap = [
    'headlamp (led)' => 'Fenerë LED','headlamp led'=>'Fenerë LED','led headlamp'=>'Fenerë LED',
    'automatic air conditioner'=>'Kondicioner automatik','air conditioner'=>'Kondicioner',
    'rear camera'=>'Kamerë mbrapa','navigation'=>'Navigim','leather seats'=>'Ulëse lëkure',
    'heated seats'=>'Ulëse të ngrohura','ventilated seat'=>'Ulëse të ventilueshme','smart key'=>'Çelës smart',
    'parking detection sensor'=>'Sensor parkimi','sunroof'=>'Tavan panoramik','panoramic sunroof'=>'Tavan panoramik',
    'there is'=>'',
  ];
  $negativePatterns = '~(does ?n\'?t exist|does not exist|not (equipped|available|present)|no\b|none\b|without|미장착|미적용|없음|없어요|무)~iu';

  $cleanOpt = function($t) use ($optMap,$negativePatterns){
    $t=(string)$t;
    if (preg_match($negativePatterns, $t)) return null;
    $t=preg_replace('~\bthere (is|are)\b~i','',$t);
    $t=preg_replace('~\s+있(습니다|음|어요)$~u','',$t);
    $t=trim(preg_replace('~\s+~',' ',$t));
    $key=mb_strtolower($t); if(isset($optMap[$key])) $t=$optMap[$key];
    if($t===''||mb_strlen($t)<2) return null;
    return mb_strtoupper(mb_substr($t,0,1)).mb_substr($t,1);
  };
  $opts=[]; foreach($options as $o){ $o2=$cleanOpt($o); if($o2 && !in_array($o2,$opts,true)) $opts[]=$o2; }
  $opts=array_slice($opts,0,18);

  $priceOut = isset($vehicle->cmimi_eur) && $vehicle->cmimi_eur !== null
    ? '€'.number_format((int)$vehicle->cmimi_eur, 0, ',', '.')
    : 'Kontakto Pronarin';

  $km = $vehicle->kilometrazhi_km ?? null;
  if (!$km && isset($vehicle->mileage)) $km = preg_replace('/\D+/', '', (string)$vehicle->mileage);

  $colorRaw = trim((string)($vehicle->ngjyra ?? $vehicle->color ?? ''));
  $colorOut = $colorRaw !== '' ? $colorRaw : 'Unike';
  if ($colorOut === 'Unike' || $colorOut === '—') {
      $blob = mb_strtolower($title.' '.implode(' ', $opts).' '.(string)($vehicle->varianti ?? $vehicle->grade ?? ''));
      if (preg_match('~(silver|argent|platinum|chrome|실버|은색)~u', $blob)) $colorOut = 'Argjendtë';
      elseif (preg_match('~(grey|gray|graphite|gunmetal|charcoal|slate|titanium|magnetic|그레이|회색)~u', $blob)) $colorOut = 'Gri';
  }
@endphp

<link rel="preload" as="image"
      imagesrcset="{{ $mainX1 }} 1x, {{ $mainX2 }} 2x"
      imagesizes="(max-width: 1024px) 100vw, 66vw"
      href="{{ $mainX2 }}">

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <a href="{{ route('index') }}" class="text-brand-muted hover:text-white inline-flex items-center gap-2">
    <span class="inline-block rounded-md bg-white/5 px-2 py-1 ring-1 ring-white/10">←</span>
    Kthehu te Makinat
  </a>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-6">
    <!-- Left: Gallery -->
    <div class="lg:col-span-2 space-y-3">
      <div class="gallery-main relative ring-1 ring-white/5 shadow-2xl shadow-black/30">
        <img id="mainImage"
             src="{{ $mainX1 }}"
             srcset="{{ $mainX1 }} 1x, {{ $mainX2 }} 2x"
             sizes="(max-width: 1024px) 100vw, 66vw"
             alt="{{ $title }}"
             decoding="async"
             fetchpriority="high"
             class="w-full h-full object-cover cursor-pointer">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/5 via-transparent to-black/5"></div>
      </div>

      <div class="thumbs">
        @foreach($imagesHi as $i => $img)
          <img
            src="{{ $img['x1'] }}"
            srcset="{{ $img['x1'] }} 1x, {{ $img['x2'] }} 2x"
            sizes="80px"
            data-idx="{{ $i }}"
            alt="img-{{ $i }}"
            class="cursor-pointer border border-transparent hover:border-brand-red shadow-sm shadow-black/30">
        @endforeach
      </div>

      <div class="pt-2">
        <h1 class="text-3xl font-extrabold tracking-tight text-white">{{ $title }}</h1>
        @if(($vehicle->varianti ?? $vehicle->grade ?? null))
          <div class="mt-1 inline-flex items-center gap-2 text-white">
            <span class="inline-flex items-center rounded-full bg-white/5 px-3 py-1 text-md ring-1 ring-white/10">{{ $vehicle->varianti ?? $vehicle->grade }}</span>
          </div>
        @endif
      </div>

      <!-- Price + Report -->
      <div class="spec-card mt-2 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="text-2xl font-bold text-white">{{ $priceOut }}</div>
          <div class="flex items-center gap-3">
            <span class="text-xs text-green-400">(çmimi deri në portin e Durrësit)</span>

            @auth
              @php $fav = $vehicle->isFavoritedBy(auth()->user()); @endphp
              <form method="POST" action="{{ route('favorites.toggle', $vehicle) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md {{ $fav ? 'bg-red-700 hover:bg-red-600' : 'bg-white/5 hover:bg-white/10' }} text-white text-xs font-semibold border border-white/10">
                  <svg viewBox="0 0 24 24"
                       class="size-6"
                       fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                       style="filter: drop-shadow(0 0 6px rgba(239,68,68,.35));">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.78 0L12 5.62l-1.02-1.02a5.5 5.5 0 1 0-7.78 7.78L12 21l8.8-8.62a5.5 5.5 0 0 0 0-7.78z"/>
                  </svg>
                  <span>{{ $fav ? 'Në të preferuarat' : 'Shto te të preferuarat' }}</span>
                </button>
              </form>
            @else
              <a href="{{ route('login') }}"
                 class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white/5 hover:bg-white/10 text-white text-xs font-semibold border border-white/10">
                <svg viewBox="0 0 24 24"
                     class="w-4 h-4"
                     fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                     style="filter: drop-shadow(0 0 6px rgba(239,68,68,.35));">
                  <path d="M20.8 4.6a5.5 5.5 0 0 0-7.78 0L12 5.62l-1.02-1.02a5.5 5.5 0 1 0-7.78 7.78L12 21l8.8-8.62a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <span>Ruaj si të preferuar</span>
              </a>
            @endauth

            @if($reportUrl)
              <a href="{{ $reportUrl }}" target="_blank" rel="noopener"
                 class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-xs font-semibold shadow-lg shadow-green-900/30">
                 Shiko Raportin e Performancës
              </a>
            @endif
          </div>
        </div>
      </div>

      @if($opts)
        <div class="spec-card p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-white">Opsionet / Pajisje</h2>
            <span class="text-xs text-brand-muted">{{ count($opts) }} opsione</span>
          </div>
          <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach($opts as $o)
              <li class="flex items-center gap-2">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500"></span>
                <span class="text-sm text-gray-200">{{ $o }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
<div class="spec-card p-5 mt-6" id="loanCalc">
  <h2 class="text-lg font-semibold text-white mb-4">Kalkulatori i Kredisë</h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <label class="block">
      <span class="text-sm text-brand-muted">Shuma e kredisë</span>
      <div class="mt-1 flex rounded-md shadow-sm overflow-hidden">
        <span class="inline-flex items-center px-3 bg-[#0f1115] border border-[#252c3f] text-gray-300">€</span>
        <input id="lc_amount" type="number" min="0" step="100"
               class="flex-1 bg-[#0f1115] border border-l-0 border-[#252c3f] text-white px-3 py-2 outline-none"
               placeholder="p.sh. 10,000">
      </div>
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Afati</span>
      <div class="mt-1 flex gap-2">
        <input id="lc_years" type="number" min="1" max="10" step="1"
               class="w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded-md"
               placeholder="vjet (p.sh. 5)">
      </div>
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Norma e interesit (vjetore)</span>
      <div class="mt-1 flex rounded-md shadow-sm overflow-hidden">
        <input id="lc_rate" type="number" min="0" step="0.1"
               class="flex-1 bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 outline-none"
               placeholder="p.sh. 8">
        <span class="inline-flex items-center px-3 bg-[#0f1115] border border-l-0 border-[#252c3f] text-gray-300">%</span>
      </div>
    </label>
  </div>

  <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]">
      <div class="text-sm text-brand-muted">Kësti mujor i vlerësuar</div>
      <div id="lc_monthly" class="text-2xl font-bold text-white mt-1">—</div>
    </div>
    <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]">
      <div class="text-sm text-brand-muted">Interesi total</div>
      <div id="lc_interest" class="text-xl font-semibold text-white mt-1">—</div>
    </div>
    <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]">
      <div class="text-sm text-brand-muted">Kosto totale e kredisë</div>
      <div id="lc_total" class="text-xl font-semibold text-white mt-1">—</div>
    </div>
  </div>

  <div class="mt-4 flex items-center justify-between">
    <div class="text-xs text-brand-muted">Llogaritje orientuese. Kushtet mund të ndryshojnë sipas bankës.</div>
    <button id="lc_toggle" type="button"
            class="text-sm text-red-400 hover:text-red-300 underline underline-offset-4">
      Shfaq planin e amortizimit
    </button>
  </div>

  <div id="lc_table_wrap" class="mt-3 hidden overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-brand-muted border-b border-[#252c3f]">
          <th class="text-left py-2 pr-4">#</th>
          <th class="text-right py-2 pr-4">Kësti</th>
          <th class="text-right py-2 pr-4">Interesi</th>
          <th class="text-right py-2 pr-4">Principal</th>
          <th class="text-right py-2">Balanca</th>
        </tr>
      </thead>
      <tbody id="lc_rows"></tbody>
    </table>
  </div>
</div>
      <!-- Specs -->
      <div class="spec-card p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 spec-tiles">
          @php
            $kmNum = $vehicle->kilometrazhi_km ?? null;
            if (!$kmNum && isset($vehicle->mileage)) $kmNum = preg_replace('/\D+/', '', (string)$vehicle->mileage);
          @endphp
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Marka</div><div class="text-white font-semibold">{{ $vehicle->prodhuesi ?? $vehicle->manufacturer ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Modeli</div><div class="text-white font-semibold">{{ $vehicle->modeli ?? $vehicle->model ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Varianti</div><div class="text-white font-semibold">{{ $vehicle->varianti ?? $vehicle->grade ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Viti i Prodhimit</div><div class="text-white font-semibold">{{ $vehicle->viti ?? ($vehicle->year ?? '—') }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Kilometrazhi</div><div class="text-white font-semibold">{{ $kmNum ? number_format($kmNum,0,',','.') . ' km' : ($vehicle->mileage ?? '—') }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Karburanti</div><div class="text-white font-semibold">{{ $vehicle->karburanti ?? $vehicle->fuel ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Transmisioni</div><div class="text-white font-semibold">{{ $vehicle->transmisioni ?? $vehicle->transmission ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Ngjyra</div><div class="text-white font-semibold">{{ $colorOut ?: '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">Ulëset</div><div class="text-white font-semibold">{{ $vehicle->uleset ?? $vehicle->seats ?? '—' }}</div></div>
          <div class="bg-[#0f1115] p-4 rounded-md border border-[#252c3f]"><div class="text-sm text-brand-muted">VIN</div><div class="text-white font-semibold break-all">{{ $vehicle->vin ?? '—' }}</div></div>
        </div>
      </div>
      
    </div>

    <!-- Right: Contact -->
    <aside class="contact-card p-5 mt-8 lg:mt-0 h-max">
      <div class="text-sm text-brand-muted mb-2">Kontakt & Inspektim</div>
      <a
        class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-md shadow-lg shadow-emerald-900/30"
        href="https://wa.me/38348661161?text={{ urlencode($waText) }}"
        target="_blank" rel="noopener"
      >
        <img
          src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/1022px-WhatsApp.svg.png"
          alt=""
          class="h-5 w-5 shrink-0 object-contain"
          loading="lazy"
        />
        <span>WhatsApp</span>
      </a>
      <div class="mt-4 space-y-2 text-sm">
        <div class="bg-[#0f1115] border border-[#252c3f] rounded-md p-3"><div class="text-brand-muted">Email</div><div class="text-white font-medium">korean.cars11@gmail.com</div></div>
        <div class="bg-[#0f1115] border border-[#252c3f] rounded-md p-3"><div class="text-brand-muted">Telefon</div><div class="text-white font-medium">+383 48 661 161</div></div>
        <div class="bg-[#0f1115] border border-[#252c3f] rounded-md p-3">
          <div class="text-brand-muted">Adresa</div>
          <div class="text-white font-medium">Rr. Abdyl Frasheri, 8. <br>Afër Sallës 1 Tetori, Prishtinë, 10000</div>
        </div>
      </div>
    </aside>
  </div>
</div>

<!-- LIGHTBOX with zoom -->
<div id="lb" class="lb" aria-modal="true" role="dialog">
  <div class="lb-inner">
    <div id="lbStage" class="lb-stage">
      <img id="lbImg" class="lb-img" src="" alt="preview" decoding="async">
      <button id="lbPrev" class="lb-btn lb-prev" aria-label="Prev">❮</button>
      <button id="lbNext" class="lb-btn lb-next" aria-label="Next">❯</button>
      <button id="lbClose" class="lb-btn lb-close" aria-label="Close">✕</button>
    </div>
    <div id="lbStrip" class="lb-strip">
      @foreach($imagesHi as $i => $img)
        <img src="{{ $img['x1'] }}" srcset="{{ $img['x1'] }} 1x, {{ $img['x2'] }} 2x" data-idx="{{ $i }}" alt="thumb-{{ $i }}">
      @endforeach
    </div>
  </div>
</div>
<script>
(() => {
  const images = @json($imagesLB); // use ORIGINALS in LB
  const main   = document.getElementById('mainImage');
  const thumbs = document.querySelectorAll('.thumbs img');

  // Lightbox nodes
  const lb       = document.getElementById('lb');
  const lbImg    = document.getElementById('lbImg');
  const lbStage  = document.getElementById('lbStage');
  const lbStrip  = document.getElementById('lbStrip');
  const btnPrev  = document.getElementById('lbPrev');
  const btnNext  = document.getElementById('lbNext');
  const btnClose = document.getElementById('lbClose');

  let idx = 0;
  let scale = 1, baseScale = 1, minScale = 1, maxScale = 4;
  let tx = 0, ty = 0, dragging = false, sx = 0, sy = 0;

  // pinch state
  let pinch = { active:false, d0:0, cx:0, cy:0, tx0:0, ty0:0, s0:1 };

  const apply = () => {
    lbImg.style.transform = `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px)) scale(${scale})`;
  };
  const clampPan = () => {
    const r = lbStage.getBoundingClientRect();
    const maxX = (r.width  * (scale - 1)) / 2;
    const maxY = (r.height * (scale - 1)) / 2;
    tx = Math.max(-maxX, Math.min(maxX, tx));
    ty = Math.max(-maxY, Math.min(maxY, ty));
  };
  const setActiveThumb = () => {
    Array.from(lbStrip.querySelectorAll('img')).forEach((t,i)=>{
      t.classList.toggle('active', i===idx);
      if(i===idx) t.scrollIntoView({inline:'center',block:'nearest',behavior:'smooth'});
    });
  };

  function computeBaseScale() {
    const s = lbStage.getBoundingClientRect();
    const nw = lbImg.naturalWidth || lbImg.width;
    const nh = lbImg.naturalHeight || lbImg.height;
    if (!nw || !nh) { baseScale = minScale = 1; maxScale = 4; return; }

    baseScale = Math.min(s.width / nw, s.height / nh);
    minScale = baseScale;

    const oneToOne = Math.max(1, baseScale);
    maxScale = Math.max(oneToOne, baseScale * 8);
  }

  function show(iNew){
    idx = (iNew + images.length) % images.length;
    lbImg.src = images[idx] || '';
    tx = ty = 0;

    const onLoad = () => {
      computeBaseScale();
      scale = baseScale;
      apply();
      lbImg.removeEventListener('load', onLoad);
    };
    lbImg.addEventListener('load', onLoad);
    setActiveThumb();
  }

  function openAt(i){
    if(!images.length) return;
    lb.classList.add('show'); document.body.style.overflow = 'hidden';
    show(i);
  }
  function closeLb(){
    lb.classList.remove('show'); document.body.style.overflow = '';
  }

  // Openers
  main?.addEventListener('click', ()=>openAt(0));
  thumbs.forEach(t=>t.addEventListener('click', e=>openAt(parseInt(e.currentTarget.dataset.idx||0,10))));

  // Controls
  btnPrev.onclick = ()=>show(idx-1);
  btnNext.onclick = ()=>show(idx+1);
  btnClose.onclick= closeLb;

  // Strip clicks
  lbStrip.querySelectorAll('img').forEach(t=>t.addEventListener('click', e=>{
    show(parseInt(e.currentTarget.dataset.idx||0,10));
  }));

  // Backdrop click to close
  lb.addEventListener('click', e=>{ if(e.target===lb) closeLb(); });

  // Keyboard
  document.addEventListener('keydown', e=>{
    if(!lb.classList.contains('show')) return;
    if(e.key==='Escape') closeLb();
    if(e.key==='ArrowLeft') show(idx-1);
    if(e.key==='ArrowRight') show(idx+1);
  });

  // --- Zoom helpers ---
  function zoomAt(clientX, clientY, nextScale){
    nextScale = Math.max(minScale, Math.min(maxScale, nextScale));
    const rect = lbStage.getBoundingClientRect();
    const cx = clientX - (rect.left + rect.width/2);
    const cy = clientY - (rect.top  + rect.height/2);
    const k = nextScale / scale;
    tx = cx - (cx - tx) * k;
    ty = cy - (cy - ty) * k;
    scale = nextScale;
    clampPan(); apply();
  }

  // Wheel zoom
  lbStage.addEventListener('wheel', e=>{
    if(!lb.classList.contains('show')) return;
    e.preventDefault();
    const delta = e.deltaY > 0 ? -1 : 1;
    const factor = 1 + (delta * 0.18);
    zoomAt(e.clientX, e.clientY, scale * factor);
  }, { passive:false });

  // Double-click toggle
  lbStage.addEventListener('dblclick', e=>{
    const target = Math.max(minScale * 2.2, 1);
    const next = (Math.abs(scale - minScale) < 0.01) ? target : minScale;
    zoomAt(e.clientX, e.clientY, next);
  });

  // Drag to pan
  lbStage.addEventListener('mousedown', e=>{
    if(!lb.classList.contains('show')) return;
    dragging = true; sx = e.clientX; sy = e.clientY;
    lbStage.classList.add('grabbing');
  });
  window.addEventListener('mousemove', e=>{
    if(!dragging) return;
    tx += (e.clientX - sx);
    ty += (e.clientY - sy);
    sx = e.clientX; sy = e.clientY;
    clampPan(); apply();
  });
  window.addEventListener('mouseup', ()=>{
    dragging = false; lbStage.classList.remove('grabbing');
  });

  // Touch pinch & pan
  function dist(a,b){ const dx=a.clientX-b.clientX, dy=a.clientY-b.clientY; return Math.hypot(dx,dy); }
  lbStage.addEventListener('touchstart', e=>{
    if(e.touches.length===2){
      pinch.active = true;
      pinch.d0 = dist(e.touches[0], e.touches[1]);
      const rect = lbStage.getBoundingClientRect();
      pinch.cx = ((e.touches[0].clientX + e.touches[1].clientX)/2);
      pinch.cy = ((e.touches[0].clientY + e.touches[1].clientY)/2);
      pinch.tx0 = tx; pinch.ty0 = ty; pinch.s0 = scale;
    } else if(e.touches.length===1){
      dragging = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
    }
  }, {passive:false});

  lbStage.addEventListener('touchmove', e=>{
    if(pinch.active && e.touches.length===2){
      e.preventDefault();
      const d1 = dist(e.touches[0], e.touches[1]);
      const ratio = d1 / (pinch.d0 || 1);
      const nextScale = Math.max(minScale, Math.min(maxScale, pinch.s0 * ratio));
      zoomAt(pinch.cx, pinch.cy, nextScale);
    } else if(dragging && e.touches.length===1){
      e.preventDefault();
      const x = e.touches[0].clientX, y = e.touches[0].clientY;
      tx += (x - sx); ty += (y - sy);
      sx = x; sy = y;
      clampPan(); apply();
    }
  }, {passive:false});

  window.addEventListener('touchend', ()=>{
    if (pinch.active && (event.touches?.length ?? 0) < 2) pinch.active = false;
    if ((event.touches?.length ?? 0) === 0) dragging = false;
    lbStage.classList.remove('grabbing');
  }, {passive:true});
})();
(() => {
  const cmimi = Number({{ (int)($vehicle->cmimi_eur ?? 0) }});
  const defaultAmount = cmimi > 0 ? cmimi : 10000;

  const $ = (id) => document.getElementById(id);
  const fmt = new Intl.NumberFormat('sq-AL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

  const amount = $('lc_amount');
  const years  = $('lc_years');
  const rate   = $('lc_rate');
  const monthly= $('lc_monthly');
  const interest=$('lc_interest');
  const total  = $('lc_total');
  const rows   = $('lc_rows');
  const wrap   = $('lc_table_wrap');
  const toggle = $('lc_toggle');

  amount.value = defaultAmount;
  years.value  = 5;
  rate.value   = 8.0;

  function calcPayment(P, annualRate, nYears){
    const r = (annualRate/100)/12;
    const n = Math.max(1, Math.round(nYears*12));
    if (r === 0) return { m: P/n, n, r };
    const m = P * (r * Math.pow(1+r, n)) / (Math.pow(1+r, n) - 1);
    return { m, n, r };
  }

  function render(){
    const P = Math.max(0, Number(amount.value || 0));
    const y = Math.max(0.1, Number(years.value || 0));
    const R = Math.max(0, Number(rate.value || 0));
    const { m, n, r } = calcPayment(P, R, y);

    const totalPaid = m * n;
    const totInterest = totalPaid - P;

    monthly.textContent  = fmt.format(m);
    interest.textContent = fmt.format(totInterest);
    total.textContent    = fmt.format(totalPaid);

    rows.innerHTML = '';
    let balance = P;
    for(let i=1; i<=n && i<=120; i++){
      const interestPart = balance * r;
      const principalPart = Math.max(0, m - interestPart);
      balance = Math.max(0, balance - principalPart);

      const tr = document.createElement('tr');
      tr.className = i % 2 ? 'bg-[#0e131d]' : '';
      tr.innerHTML = `
        <td class="py-2 pr-4 text-white/80">${i}</td>
        <td class="py-2 pr-4 text-right text-white">${fmt.format(m)}</td>
        <td class="py-2 pr-4 text-right text-white/90">${fmt.format(interestPart)}</td>
        <td class="py-2 pr-4 text-right text-white/90">${fmt.format(principalPart)}</td>
        <td class="py-2 text-right text-white">${fmt.format(balance)}</td>
      `;
      rows.appendChild(tr);
    }
  }

  ['input','change'].forEach(ev=>{
    amount.addEventListener(ev, render);
    years.addEventListener(ev, render);
    rate.addEventListener(ev, render);
  });

  toggle.addEventListener('click', () => {
    const open = wrap.classList.toggle('hidden') === false;
    toggle.textContent = open ? 'Fshih planin e amortizimit' : 'Shfaq planin e amortizimit';
  });

  render();
})();
</script>
@endsection
