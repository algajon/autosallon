{{-- vehicles/_grid.blade.php --}}

<div data-header>
  @php
    $from = $from ?? 0; $to = $to ?? 0;
    $ttl = $vehicles->total();
  @endphp
  Duke shfaqur <span class="text-white font-semibold">{{ $ttl ? $from : 0 }}–{{ $ttl ? $to : 0 }}</span>
  nga <span class="text-white font-semibold">{{ number_format($ttl) }}</span> rezultate
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mt-3">
  @forelse($vehicles as $v)
    @php
      $imgs = is_array($v->images) ? $v->images : array_values(array_filter(array_map('trim', explode(';', (string)$v->images))));
      $img  = $imgs[0] ?? 'https://via.placeholder.com/640x360?text=No+Image';
      $title = $v->display_title ?? trim(($v->viti ? $v->viti.' ' : '').$v->prodhuesi.' '.$v->modeli);
      $km = $v->mileage_formatted ?? ($v->kilometrazhi_km ? number_format($v->kilometrazhi_km,0,',','.') . ' km' : '—');
      $cmim = $v->price_formatted ?? ($v->cmimi_eur ? '€'.number_format($v->cmimi_eur,0,',','.') : '—');
    @endphp

    <div class="card overflow-hidden">
      <a href="{{ route('listing.show', $v->id) }}" class="block">
        <div class="relative w-full pb-[62%] bg-gradient-to-br from-gray-800 to-gray-700">
          <img src="{{ $img }}" alt="{{ $title }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-center">
        </div>
        <div class="p-5">
          <h3 class="text-lg font-bold text-white mb-1">{{ $title }}</h3>
          @if($v->varianti)
            <p class="text-brand-muted mb-3">{{ $v->varianti }}</p>
          @endif
          <div class="flex flex-wrap gap-2 text-xs mb-4">
            @if($km)                 <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $km }}</span>@endif
            @if($v->transmisioni)    <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->transmisioni }}</span>@endif
            @if($v->karburanti)      <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->karburanti }}</span>@endif
            @if($v->ngjyra)          <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->ngjyra }}</span>@endif
          </div>
          <div class="flex items-center justify-between">
            <div class="text-white font-bold text-lg">{{ $cmim }}</div>
            <span class="text-brand-red font-semibold hover:text-brand-red-dark">Shiko Detajet</span>
          </div>
        </div>
      </a>
    </div>
  @empty
    <div class="col-span-full text-center py-12">
      <div class="text-gray-400 text-lg mb-4">Nuk u gjet asnjë automjet</div>
      <p class="text-gray-500">Provoni të ndryshoni filtrat ose fjalët kyçe</p>
    </div>
  @endforelse
</div>

<div class="mt-8">
  {{ $vehicles->withQueryString()->links() }}
</div>
