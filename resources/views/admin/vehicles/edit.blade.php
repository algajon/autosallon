@extends('layouts.app')

@section('content')
  <style>
    body { font-family:'Bahnschrift','Inter',sans-serif; }
    h1,h2,h3,h4,h5,h6,.heading { font-family:'Montserrat',sans-serif; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
    .card { background:#0f1115; border:1px solid #262c3f; border-radius:14px; }
    .input, .select, .textarea { background:#0b0f16; border:1px solid #263042; color:#e5e7eb; }
    .help { color:#9ca3af; font-size:.85rem }
    .thumb { position:relative; padding-bottom:62%; background:#0b0f16; border:1px solid #263042; border-radius:10px; overflow:hidden }
    .thumb img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover }
  </style>

  <div class="max-w-4xl mx-auto py-24 px-4 sm:px-6 lg:px-8">
    <div class="card shadow p-6">
      <h2 class="text-2xl font-bold text-white mb-6">Edit Vehicle</h2>

      <form method="POST" action="{{ route('admin.vehicles.update', $vehicle) }}">
        @csrf
        @method('PUT')

        @if ($errors->any())
          <div class="bg-red-900/20 border border-red-700 text-red-300 px-4 py-3 rounded mb-6">
            <ul class="list-disc list-inside">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- BASIC INFO --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300">Prodhuesi (Marka)</label>
            <input name="prodhuesi" type="text"
                   value="{{ old('prodhuesi', $vehicle->prodhuesi) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
            <p class="help mt-1">p.sh. BMW — (opsionale: <code>manufacturer</code> mirror)</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Modeli</label>
            <input name="modeli" type="text"
                   value="{{ old('modeli', $vehicle->modeli) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
            <p class="help mt-1">p.sh. X5 — (opsionale: <code>model</code> mirror)</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Varianti (Grade/Trim)</label>
            <input name="varianti" type="text"
                   value="{{ old('varianti', $vehicle->varianti) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
            <p class="help mt-1">p.sh. 30d M Sport — (opsionale: <code>grade</code> mirror)</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Viti</label>
            <input name="viti" type="number" inputmode="numeric" min="1980" max="2099"
                   value="{{ old('viti', $vehicle->viti) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
        </div>

        {{-- PRICING & MILEAGE --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300">Çmimi (€)</label>
            <input name="cmimi_eur" type="number" inputmode="numeric" min="0" step="1"
                   value="{{ old('cmimi_eur', $vehicle->cmimi_eur) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Kilometrazhi (km)</label>
            <input name="kilometrazhi_km" type="number" inputmode="numeric" min="0" step="1"
                   value="{{ old('kilometrazhi_km', $vehicle->kilometrazhi_km) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Ulëset</label>
            <input name="uleset" type="number" inputmode="numeric" min="1" max="9" step="1"
                   value="{{ old('uleset', $vehicle->uleset) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
        </div>

        {{-- SPECS --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300">Karburanti</label>
            <input name="karburanti" type="text"
                   value="{{ old('karburanti', $vehicle->karburanti) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Transmisioni</label>
            <input name="transmisioni" type="text"
                   value="{{ old('transmisioni', $vehicle->transmisioni) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Ngjyra</label>
            <input name="ngjyra" type="text"
                   value="{{ old('ngjyra', $vehicle->ngjyra) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300">VIN</label>
            <input name="vin" type="text" maxlength="64"
                   value="{{ old('vin', $vehicle->vin) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Engine (cc)</label>
            <input name="engine_cc" type="number" inputmode="numeric" min="0" max="10000" step="1"
                   value="{{ old('engine_cc', $vehicle->engine_cc) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300">Listing URL (burimi)</label>
            <input name="listing_url" type="url"
                   value="{{ old('listing_url', $vehicle->listing_url) }}"
                   class="mt-1 w-full rounded-md input px-3 py-2" />
          </div>
        </div>

        {{-- OPTIONS / REPORT LINKS --}}
        <div class="mt-6">
          <label class="block text-sm font-medium text-gray-300">Opsionet / Pajisjet</label>
          <textarea name="opsionet" rows="3"
                    class="mt-1 w-full rounded-md textarea px-3 py-2"
                    placeholder="Semikolon ose rreshta: Navi; Kamera mbrapa; LED; ...">{{ old('opsionet', $vehicle->opsionet) }}</textarea>
          <p class="help mt-1">Formati i scraper-it pranohet (ndarje me ; ose rreshta).</p>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-300">Raporti URL (Car history / performance)</label>
          <textarea name="raporti_url" rows="2"
                    class="mt-1 w-full rounded-md textarea px-3 py-2"
                    placeholder="Mund të vendosësh disa URL; i pari do të përdoret nga UI">{{ old('raporti_url', $vehicle->raporti_url) }}</textarea>
          <p class="help mt-1">Vendos një ose disa URL; ndarja me ; , ose hapësirë/rreshta.</p>
        </div>

        {{-- IMAGES (TEXT) + LIVE PREVIEW --}}
        <div class="mt-8">
          <label class="block text-sm font-medium text-gray-300">Images</label>
          <textarea id="images" name="images" rows="5"
                    class="mt-1 w-full rounded-md textarea px-3 py-2"
                    placeholder="Vendos URL-t e fotove: JSON array, ose të ndara me ; , ose rreshta">{{ old('images', $imagesString ?? '') }}</textarea>
          <p class="help mt-1">Pranohen: <code>["https://...","https://..."]</code> ose URL të ndara me ; / rreshta.</p>

          <div class="mt-3 flex items-center gap-2">
            <button type="button" id="parseImages" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded-md text-sm">
              Përditëso parapamjen
            </button>
            @if(!empty($vehicle->images))
              <span class="help">Ka {{ is_array($vehicle->images) ? count($vehicle->images) : 'disa' }} foto në DB.</span>
            @endif
          </div>

          <div id="preview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            {{-- Filled by JS --}}
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-10">
          <a href="{{ route('admin.vehicles.index') }}" class="bg-gray-700 text-gray-200 px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
            Cancel
          </a>
          <button type="submit" class="bg-brand-red text-white px-4 py-2 rounded-md hover:bg-brand-red-dark transition-colors">
            Update Vehicle
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- tiny helper to preview image URLs typed in the textarea --}}
  <script>
    (function(){
      const ta = document.getElementById('images');
      const btn = document.getElementById('parseImages');
      const grid = document.getElementById('preview');

      function parse(val){
        if(!val) return [];
        const trimmed = val.trim();
        // JSON array
        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
          try {
            const arr = JSON.parse(trimmed);
            if (Array.isArray(arr)) return arr.map(String).map(s => s.trim()).filter(Boolean);
          } catch(_) {}
        }
        // split by newline / ; / ,
        return (trimmed.split(/[\r\n;,]+/).map(s => s.trim()).filter(Boolean));
      }

      function render(){
        const urls = parse(ta.value);
        grid.innerHTML = '';
        urls.slice(0, 24).forEach(u => {
          const cell = document.createElement('div');
          cell.className = 'thumb';
          cell.innerHTML = `<img src="${u}" alt="">`;
          grid.appendChild(cell);
        });
        if (urls.length === 0) {
          grid.innerHTML = `<div class="text-sm text-gray-400 col-span-full">S’ka imazhe për të shfaqur.</div>`;
        }
      }

      if (btn) btn.addEventListener('click', render);
      // initial render (from server value)
      render();
    })();
  </script>
@endsection
