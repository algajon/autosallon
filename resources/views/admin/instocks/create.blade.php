@extends('layouts.app')
@section('title','Shto Në Stok')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-2xl font-bold text-white mb-6">Shto Në Stok</h1>
  <form method="POST" action="{{ route('admin.instocks.store') }}" class="space-y-4" enctype="multipart/form-data">
    @csrf
    <label class="block">
      <span class="text-sm text-brand-muted">Emri</span>
      <input name="name" value="{{ old('name') }}" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded" required>
      @error('name')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Përshkrimi</span>
      <textarea name="description" rows="4" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">{{ old('description') }}</textarea>
      @error('description')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Çmimi (EUR)</span>
      <input type="number" name="price" min="0" step="1" value="{{ old('price') }}" class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">
      @error('price')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
    </label>

    <label class="block">
      <span class="text-sm text-brand-muted">Zgjidh imazhe nga pajisja</span>
      <input type="file" name="gallery[]" accept="image/*" multiple
             class="mt-1 w-full bg-[#0f1115] border border-[#252c3f] text-white px-3 py-2 rounded">
      @error('gallery')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
      @error('gallery.*')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
      <div id="preview" class="mt-3 grid grid-cols-3 gap-2"></div>
    </label>

    <script>
      // Simple client-side preview
      (function(){
        const input = document.querySelector('input[name="gallery[]"]');
        const preview = document.getElementById('preview');
        if (!input || !preview) return;
        input.addEventListener('change', () => {
          preview.innerHTML = '';
          const files = Array.from(input.files || []);
          files.slice(0, 12).forEach(file => {
            const url = URL.createObjectURL(file);
            const img = document.createElement('img');
            img.src = url;
            img.className = 'w-full h-24 object-cover rounded border border-[#252c3f]';
            preview.appendChild(img);
          });
        });
      })();
    </script>

    <div class="pt-4">
      <button type="submit" class="bg-brand-red hover:bg-brand-red-dark text-white font-semibold px-4 py-2 rounded">Ruaj</button>
      <a href="{{ route('admin.instocks.index') }}" class="ml-3 text-brand-muted">Anulo</a>
    </div>
  </form>
</div>
@endsection
