{{-- vehicles/_card.blade.php --}}
@php
  $imgs = is_array($v->images) ? $v->images : array_values(array_filter(array_map('trim', explode(';', (string)$v->images))));
  $img  = $imgs[0] ?? 'https://via.placeholder.com/640x360?text=No+Image';
  $title = $v->display_title ?? trim(($v->viti ? $v->viti.' ' : '').($v->prodhuesi ?? $v->manufacturer ?? '').' '.($v->modeli ?? $v->model ?? ''));
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
      @if($v->varianti ?? $v->grade)
        <p class="text-brand-muted mb-3">{{ $v->varianti ?? $v->grade }}</p>
      @endif
      <div class="flex flex-wrap gap-2 text-xs mb-4">
        @if($km)                 <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $km }}</span>@endif
        @if($v->transmisioni ?? $v->transmission)    <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->transmisioni ?? $v->transmission }}</span>@endif
        @if($v->karburanti ?? $v->fuel)      <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->karburanti ?? $v->fuel }}</span>@endif
        @if($v->ngjyra ?? $v->color)          <span class="px-3 py-1 rounded-full bg-brand-dark text-gray-300">{{ $v->ngjyra ?? $v->color }}</span>@endif
      </div>
      <div class="flex items-center justify-between">
        <div class="text-white font-bold text-lg">{{ $cmim }}</div>
        <span class="text-brand-red font-semibold hover:text-brand-red-dark">Shiko Detajet</span>
      </div>
    </div>
  </a>
</div>
