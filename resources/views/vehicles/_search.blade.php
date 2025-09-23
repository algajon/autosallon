@php
  // Coerce $results into a Collection of items we can loop
  $items =
    ($results instanceof \Illuminate\Pagination\Paginator ||
     $results instanceof \Illuminate\Pagination\LengthAwarePaginator)
      ? collect($results->items())
      : (is_iterable($results ?? []) ? collect($results) : collect());

@endphp

@if($items->count())
  <div class="bg-[#101010] border border-gray-800 rounded-xl overflow-hidden shadow-2xl">
    @foreach($items as $v)
      @php
        // Support both array and object using data_get
        $imagesRaw = data_get($v, 'images');
        $imgs = is_array($imagesRaw)
                  ? $imagesRaw
                  : array_values(array_filter(array_map('trim', explode(';', (string)$imagesRaw))));
        $img  = $imgs[0] ?? 'https://via.placeholder.com/64x64?text=No+Image';

        $viti  = data_get($v, 'viti');
        $prod  = data_get($v, 'prodhuesi', data_get($v, 'manufacturer'));
        $mod   = data_get($v, 'modeli', data_get($v, 'model'));
        $title = data_get($v, 'display_title') ?? trim(($viti ? $viti.' ' : '').$prod.' '.$mod);

        $variant = data_get($v, 'varianti', data_get($v, 'grade'));
        $cmimi   = data_get($v, 'cmimi_eur');
        $price   = data_get($v, 'price_formatted') ?: ($cmimi ? '€'.number_format($cmimi,0,',','.') : null);

        $id = data_get($v, 'id');
      @endphp

      <a href="{{ route('listing.show', $id) }}"
         class="flex items-center gap-3 px-3 py-2 hover:bg-gray-900 transition-colors">
        <img src="{{ $img }}" alt="" class="w-12 h-12 rounded object-cover bg-gray-800 flex-shrink-0">
        <div class="min-w-0">
          <div class="text-sm text-white truncate">{{ $title }}</div>
          <div class="text-xs text-gray-400 truncate">
            @if($variant) <span>{{ $variant }}</span> @endif
            @if($price) <span class="ml-2 text-gray-300">{{ $price }}</span> @endif
          </div>
        </div>
      </a>
    @endforeach
  </div>
@else
  <div class="bg-[#101010] border border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-400">
    Nuk u gjet asgjë për “{{ $q }}”.
  </div>
@endif
