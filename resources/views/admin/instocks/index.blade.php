@extends('layouts.app')
@section('title','Në Stok')
@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">Në Stok</h1>
    <a href="{{ route('admin.instocks.create') }}" class="bg-brand-red hover:bg-brand-red-dark text-white font-semibold px-4 py-2 rounded">Shto</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($items as $item)
      @php
        $img = $item->main_image_url;
      @endphp
      <div class="bg-brand-card border border-gray-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="relative w-full aspect-[4/3] bg-gradient-to-br from-gray-800 to-gray-700">
          <img src="{{ $img }}" alt="{{ $item->name }}" class="absolute inset-0 w-full h-full object-cover">
        </div>
        <div class="p-4">
          <div class="text-white font-bold text-lg mb-1">{{ $item->name }}</div>
          <div class="text-brand-muted text-sm line-clamp-2">{{ $item->description }}</div>
          <div class="mt-3 text-white font-semibold">
            @php $p = $item->price; @endphp
            <span>{{ $p !== null ? ('€'.number_format((int)$p,0,',','.')) : 'Çmimi sipas marrëveshjes' }}</span>
          </div>
          <div class="mt-4 flex items-center gap-3">
            <a href="{{ route('admin.instocks.edit', $item) }}" class="px-3 py-1.5 rounded bg-white/10 text-white text-sm">Edito</a>
            <form method="POST" action="{{ route('admin.instocks.destroy', $item) }}" onsubmit="return confirm('Fshi?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="px-3 py-1.5 rounded bg-red-600 text-white text-sm">Fshi</button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="col-span-full text-brand-muted">Asnjë artikull në stok.</div>
    @endforelse
  </div>

  <div class="mt-8">{{ $items->links() }}</div>
</div>
@endsection
