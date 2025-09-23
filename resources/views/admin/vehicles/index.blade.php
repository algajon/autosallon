@extends('layouts.app')

@section('content')
  <style>
    body { font-family: 'Bahnschrift','Inter',sans-serif; }
    h1,h2,h3,h4,h5,h6,.heading { font-family:'Montserrat',sans-serif; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
    .card { background:#0f1115; border:1px solid #262c3f; border-radius:14px; }
  </style>

  <div class="max-w-7xl mx-auto py-36 px-4 sm:px-6 lg:px-8">
    @if (session('success'))
      <div class="bg-green-900/20 border border-green-700 text-green-300 px-4 py-3 rounded mb-6">
        {{ session('success') }}
      </div>
    @endif

    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-white">Vehicle Management</h2>
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.instocks.create') }}" class="bg-white/10 text-white px-4 py-2 rounded-md border border-white/10 hover:bg-white/15 transition-colors">
          Add In-Stock
        </a>
        <a href="{{ route('admin.vehicles.create') }}" class="bg-brand-red text-white px-4 py-2 rounded-md hover:bg-brand-red-dark transition-colors">
          Add New Vehicle
        </a>
      </div>
    </div>

    @php
      $instocks = $instocks ?? \App\Models\InStock::latest()->get();
    @endphp
    @if(isset($instocks) && $instocks->count() > 0)
      <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold text-white">Makinat në Stok</h3>
          <a href="{{ route('admin.instocks.create') }}" class="text-sm bg-white/10 text-white px-3 py-1.5 rounded border border-white/10 hover:bg-white/15">Shto</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-2">
          @foreach($instocks as $item)
            @php
              $img = $item->main_image_url ?? 'https://via.placeholder.com/640x360?text=Në+Stok';
            @endphp
            <div class="card overflow-hidden">
              <div class="relative w-full pb-[56.25%] bg-gradient-to-br from-gray-800 to-gray-700">
                <img src="{{ $img }}" alt="{{ $item->name }}" class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute top-2 left-2 text-xs bg-white/10 backdrop-blur px-2 py-1 rounded border border-white/20 text-white">NË STOK</div>
              </div>
              <div class="p-4">
                <h4 class="text-lg font-semibold text-white mb-1 line-clamp-1">{{ $item->name }}</h4>
                <div class="flex flex-wrap gap-2 text-xs mb-3">
                  @php $p = $item->price; @endphp
                  <span class="px-2.5 py-1 rounded-full bg-[#0b0f16] border border-[#263042] text-gray-200">{{ $p !== null ? ('€'.number_format((int)$p,0,',','.')) : '—' }}</span>
                </div>
                <div class="flex space-x-2">
                  <a href="{{ route('admin.instocks.edit', $item) }}"
                     class="flex-1 bg-gray-700 text-white text-center py-2 px-3 rounded text-sm hover:bg-gray-600 transition-colors">
                    View
                  </a>
                  <a href="{{ route('admin.instocks.edit', $item) }}"
                     class="flex-1 bg-brand-red text-white text-center py-2 px-3 rounded text-sm hover:bg-brand-red-dark transition-colors">
                    Edit
                  </a>
                  <form method="POST" action="{{ route('admin.instocks.destroy', $item) }}" class="flex-1"
                        onsubmit="return confirm('Are you sure you want to delete this item?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full bg-red-700 text-white py-2 px-3 rounded text-sm hover:bg-red-800 transition-colors">
                      Delete
                    </button>
                  </form>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="border-t border-[#262c3f] my-8"></div>

    @if($vehicles->count() > 0)
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($vehicles as $v)
          @php
            // Build a clean title from DB fields
            $title = trim(
              ($v->viti ? $v->viti.' ' : '').
              ($v->prodhuesi ?? '').
              ' '.
              ($v->modeli ?? '')
            );
            if (!empty($v->varianti)) $title .= ' · '.$v->varianti;

            // Format price / km
            $cmim = isset($v->cmimi_eur) && $v->cmimi_eur !== null
              ? '€'.number_format((int)$v->cmimi_eur,0,',','.')
              : '—';
            $km = isset($v->kilometrazhi_km) && $v->kilometrazhi_km !== null
              ? number_format((int)$v->kilometrazhi_km,0,',','.').' km'
              : '—';

            // Parse images regardless of storage format (JSON / semicolon / newline)
            $imgs = [];
            if (is_array($v->images)) {
              $imgs = array_values(array_filter($v->images));
            } elseif (is_string($v->images) && $v->images !== '') {
              $raw = trim($v->images);
              if (\Illuminate\Support\Str::startsWith($raw,'[') && \Illuminate\Support\Str::endsWith($raw,']')) {
                $arr = json_decode($raw,true);
                if (is_array($arr)) $imgs = array_values(array_filter(array_map('trim',$arr)));
              } else {
                $parts = preg_split('/[\r\n;,]+/u',$raw) ?: [];
                $imgs  = array_values(array_filter(array_map('trim',$parts)));
              }
            }
            $img = $imgs[0] ?? 'https://via.placeholder.com/640x360?text=No+Image';
          @endphp

          <div class="card overflow-hidden">
            <div class="relative w-full pb-[56.25%] bg-gradient-to-br from-gray-800 to-gray-700">
              <img src="{{ $img }}" alt="{{ $title }}" class="absolute inset-0 w-full h-full object-cover">
              @if($v->listing_url)
                <a href="{{ $v->listing_url }}" target="_blank" rel="noopener"
                   class="absolute top-2 right-2 text-xs bg-white/10 backdrop-blur px-2 py-1 rounded border border-white/20 text-white hover:bg-white/20">
                  Source
                </a>
              @endif
            </div>

            <div class="p-4">
              <h3 class="text-lg font-semibold text-white mb-1">{{ $title ?: '—' }}</h3>
              <div class="flex flex-wrap gap-2 text-xs mb-3">
                <span class="px-2.5 py-1 rounded-full bg-[#0b0f16] border border-[#263042] text-gray-200">{{ $cmim }}</span>
                @if(!empty($v->karburanti))
                  <span class="px-2.5 py-1 rounded-full bg-[#0b0f16] border border-[#263042] text-gray-300">{{ $v->karburanti }}</span>
                @endif
                @if(!empty($v->transmisioni))
                  <span class="px-2.5 py-1 rounded-full bg-[#0b0f16] border border-[#263042] text-gray-300">{{ $v->transmisioni }}</span>
                @endif
                @if($km !== '—')
                  <span class="px-2.5 py-1 rounded-full bg-[#0b0f16] border border-[#263042] text-gray-300">{{ $km }}</span>
                @endif
              </div>

              <div class="flex space-x-2">
                {{-- Admin "show" -> your controller redirects to the public detail --}}
                <a href="{{ route('admin.vehicles.show', $v) }}"
                   class="flex-1 bg-gray-700 text-white text-center py-2 px-3 rounded text-sm hover:bg-gray-600 transition-colors">
                  View
                </a>
                <a href="{{ route('admin.vehicles.edit', $v) }}"
                   class="flex-1 bg-brand-red text-white text-center py-2 px-3 rounded text-sm hover:bg-brand-red-dark transition-colors">
                  Edit
                </a>
                <form method="POST" action="{{ route('admin.vehicles.destroy', $v) }}" class="flex-1"
                      onsubmit="return confirm('Are you sure you want to delete this vehicle?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit"
                          class="w-full bg-red-700 text-white py-2 px-3 rounded text-sm hover:bg-red-800 transition-colors">
                    Delete
                  </button>
                </form>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="mt-8">
        {{ $vehicles->withQueryString()->links() }}
      </div>
    @else
      <div class="text-center py-12">
        <div class="text-brand-muted text-lg mb-4">No vehicles found</div>
        <a href="{{ route('admin.vehicles.create') }}" class="bg-brand-red text-white px-6 py-3 rounded-md hover:bg-brand-red-dark transition-colors">
          Add Your First Vehicle
        </a>
      </div>
    @endif
  </div>
@endsection
