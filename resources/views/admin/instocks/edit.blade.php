@extends('layouts.app')
@section('title','Edito Në Stok')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-2xl font-bold text-white mb-6">Edito: {{ $instock->name }}</h1>
  <form method="POST" action="{{ route('admin.instocks.update', $instock) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <label class="block">
      <span class="text-sm text-brand-muted">Emri</span>
      <input name="name" value="{{ old('name', $instock->name) }}" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded" required>
      @error('name')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Përshkrimi</span>
      <textarea name="description" rows="4" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">{{ old('description', $instock->description) }}</textarea>
      @error('description')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Çmimi (EUR)</span>
      <input type="number" name="price" min="0" step="1" value="{{ old('price', $instock->price) }}" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">
      @error('price')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Imazhet (URL; të ndara me ; , ose rreshta)</span>
      <textarea name="images" rows="4" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">{{ old('images', $imagesString) }}</textarea>
      @error('images')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <div class="pt-4">
      <button type="submit" class="bg-brand-red hover:bg-brand-red-dark text-white font-semibold px-4 py-2 rounded">Ruaj</button>
      <a href="{{ route('admin.instocks.index') }}" class="ml-3 text-brand-muted">Anulo</a>
    </div>
  </form>
</div>
@endsection
