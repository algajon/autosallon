@extends('layouts.app')
@section('title', 'Kërko Automjete')
@section('content')
<link rel="preconnect" href="https://cdn.jsdelivr.net" />
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
  :root{ --header-offset: 5.5rem } /* fallback; JS will measure header */
  .card { @apply bg-brand-card border border-gray-800 rounded-xl shadow-lg; }
  .facet-title { @apply text-sm font-semibold text-gray-200; }
  .facet-pill { @apply inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-800 text-gray-200 border border-gray-700 text-xs; }
  .chip { @apply inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-gray-800 text-gray-200 border border-gray-700 text-xs; }
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-lg font-semibold; }
  .btn-ghost { @apply border border-gray-700 text-gray-200 hover:bg-gray-800; }
  .btn-red { @apply bg-brand-red hover:bg-brand-red-dark text-white; }
  .input { @apply w-full bg-brand-card text-gray-200 border border-gray-700 rounded-lg px-3 py-2 placeholder:text-slate-400; }
  .selectish { @apply bg-brand-card border border-gray-700 text-gray-200 rounded-lg px-3 py-2; }
  .toggle-pill{ @apply h-9 px-3 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 }
  .toggle-on { @apply bg-gray-200 text-gray-900 border-gray-300 }
  .drawer{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; z-index:70;}
  .drawer.show{ display:block }
  .drawer-panel{ position:absolute; top:0; bottom:0; right:0; width:min(92vw,380px); background:#0b0f16; border-left:1px solid #263042; overflow:auto }
  /* Skeletons */
  .skeleton{ @apply animate-pulse rounded-md bg-gray-800/60; }
  .skeleton-img{ padding-bottom:62%; }
</style>

<div x-data="inventorySearch()" x-init="init()" class="bg-brand-black">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-16 lg:px-8 pb-10">

    <!-- Sticky toolbar (offsets your tall header) -->
    <div class="sticky z-40 -mx-4 px-4 py-3 border-b border-gray-800/80 backdrop-blur bg-black/55"
         :style="`top: calc(var(--header-offset) - 1px)`">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-3">
        <!-- Search -->
        <div class="flex-1 flex items-center gap-2">
          <div class="relative flex-1">
            <input x-model="state.q" @input.debounce.300ms="apply()"
                   type="text" class="input text-black pr-10 px-2 rounded-md"
                   placeholder="Kërko">
            <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="m21 21-4.35-4.35m0 0A7.5 7.5 0 1 0 6.5 6.5a7.5 7.5 0 0 0 10.15 10.15z"/>
            </svg>
          </div>
          <button @click="openDrawer()" class="btn btn-ghost h-10 px-3 md:hidden">
            Filtro
          </button>
        </div>

        <!-- View toggle -->
        <div class="flex items-center gap-2">
          <button @click="setView('grid')" :class="['toggle-pill', view==='grid' && 'toggle-on']" title="Grid">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8v8H3V3Zm10 0h8v8h-8V3ZM3 13h8v8H3v-8Zm10 8v-8h8v8h-8Z"/></svg>
          </button>
          <button @click="setView('list')" :class="['toggle-pill', view==='list' && 'toggle-on']" title="List">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18v2H3V5Zm0 6h18v2H3v-2Zm0 6h18v2H3v-2Z"/></svg>
          </button>
        </div>

        <!-- Sort -->
        <div class="relative" x-data="{open:false}" @keydown.escape.window="open=false">
          <button @click="open=!open" class="selectish min-w-[180px] h-10 flex items-center justify-between">
            <span x-text="sortLabel()"></span>
            <svg class="w-4 h-4 opacity-80" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.25 8.29a.75.75 0 0 1-.02-1.08Z"/></svg>
          </button>
          <div x-show="open" x-transition.origin.top.right @click.outside="open=false"
               class="absolute right-0 mt-2 w-56 bg-brand-card border border-gray-700 rounded-lg shadow-2xl overflow-hidden z-10">
            <template x-for="opt in sortOptions" :key="opt.value">
              <button @click="state.sort=opt.value; open=false; apply()"
                      class="w-full text-left px-3 py-2 hover:bg-gray-800 text-sm"
                      :class="state.sort===opt.value ? 'text-white font-semibold' : 'text-gray-200'">
                <div class="flex items-center justify-between">
                  <span x-text="opt.label"></span>
                  <span x-show="state.sort===opt.value">✔</span>
                </div>
              </button>
            </template>
          </div>
        </div>

        <!-- Reset -->
        <button @click="resetAll()" class="btn btn-ghost h-10 px-3">
          Rivendos
        </button>
      </div>

      <!-- Small stats (AJAX-updated) -->
      <div id="resultHeader" class="mt-2 text-sm text-brand-muted"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
      <!-- Sidebar facets (desktop) -->
      <aside class="lg:col-span-3 space-y-4 hidden md:block">
        <template x-for="group in facetsOrder" :key="group.key">
          <div class="card p-4">
            <div class="flex items-center justify-between">
              <div class="facet-title" x-text="group.title"></div>
              <button class="text-xs text-slate-400 hover:text-slate-200" @click="clearGroup(group.key)">Pastro</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-2 max-h-[210px] overflow-auto pr-1">
              <template x-for="f in (facets[group.key] || [])" :key="f.v">
                <button @click="toggle(group.key, f.v)" class="facet-pill"
                        :class="isOn(group.key, f.v) && 'ring-2 ring-brand-red'">
                  <span x-text="f.v"></span>
                  <span class="text-xs text-slate-400" x-text="'(' + f.c + ')'"></span>
                </button>
              </template>
            </div>

            <!-- Ranges -->
            <div x-show="group.key==='viti'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_viti" @change="apply()" class="input" type="number" min="1980" max="2099" placeholder="Min">
              <input x-model.number="state.max_viti" @change="apply()" class="input" type="number" min="1980" max="2099" placeholder="Max">
            </div>
            <div x-show="group.key==='cmimi'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_cmim" @change="apply()" class="input" type="number" min="0" placeholder="Min">
              <input x-model.number="state.max_cmim" @change="apply()" class="input" type="number" min="0" placeholder="Max">
            </div>
            <div x-show="group.key==='km'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_km" @change="apply()" class="input" type="number" min="0" placeholder="Min">
              <input x-model.number="state.max_km" @change="apply()" class="input" type="number" min="0" placeholder="Max">
            </div>
          </div>
        </template>
      </aside>

      <!-- Results -->
      <main class="lg:col-span-9">
        <!-- Active chips -->
        <div class="mb-3 flex flex-wrap gap-2">
          <template x-for="chip in chips()" :key="chip.key + ':' + chip.val">
            <button @click="removeChip(chip)" class="chip">
              <span x-text="chip.label"></span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </template>
        </div>

        <!-- Skeleton while loading -->
        <div id="skeleton" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mt-3 hidden">
          <template x-for="i in 6" :key="i">
            <div class="card overflow-hidden p-4">
              <div class="skeleton skeleton-img rounded-md"></div>
              <div class="mt-4 h-4 skeleton w-3/4"></div>
              <div class="mt-2 h-4 skeleton w-1/2"></div>
              <div class="mt-4 flex gap-2">
                <div class="h-6 skeleton w-20 rounded-full"></div>
                <div class="h-6 skeleton w-24 rounded-full"></div>
                <div class="h-6 skeleton w-16 rounded-full"></div>
              </div>
            </div>
          </template>
        </div>

        <!-- Live container we swap -->
        <div id="results">
          @include('vehicles._grid', ['vehicles'=>$vehicles, 'totalCount'=>$totalCount, 'from'=>$from, 'to'=>$to])
        </div>
      </main>
    </div>
  </div>

  <!-- Mobile drawer -->
  <div :class="['drawer', drawer && 'show']" @click.self="closeDrawer()">
    <div class="drawer-panel">
      <div class="p-4 flex items-center justify-between border-b border-gray-800">
        <div class="text-white font-semibold">Filtro</div>
        <button class="btn btn-ghost h-9 px-3" @click="closeDrawer()">Mbyll</button>
      </div>
      <div class="p-4 space-y-4">
        <template x-for="group in facetsOrder" :key="'m-'+group.key">
          <div class="card p-4">
            <div class="facet-title" x-text="group.title"></div>
            <div class="mt-3 flex flex-wrap gap-2">
              <template x-for="f in (facets[group.key] || [])" :key="f.v">
                <button @click="toggle(group.key, f.v)" class="facet-pill"
                        :class="isOn(group.key, f.v) && 'ring-2 ring-brand-red'">
                  <span x-text="f.v"></span>
                  <span class="text-xs text-slate-400" x-text="'(' + f.c + ')'"></span>
                </button>
              </template>
            </div>
            <div x-show="group.key==='viti'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_viti" @change="apply()" class="input" type="number" min="1980" max="2099" placeholder="Min">
              <input x-model.number="state.max_viti" @change="apply()" class="input" type="number" min="1980" max="2099" placeholder="Max">
            </div>
            <div x-show="group.key==='cmimi'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_cmim" @change="apply()" class="input" type="number" min="0" placeholder="Min">
              <input x-model.number="state.max_cmim" @change="apply()" class="input" type="number" min="0" placeholder="Max">
            </div>
            <div x-show="group.key==='km'" class="mt-3 grid grid-cols-2 gap-2">
              <input x-model.number="state.min_km" @change="apply()" class="input" type="number" min="0" placeholder="Min">
              <input x-model.number="state.max_km" @change="apply()" class="input" type="number" min="0" placeholder="Max">
            </div>
          </div>
        </template>
        <div class="p-4 flex gap-2">
          <button class="btn btn-red flex-1 h-10" @click="closeDrawer(); apply()">Apliko</button>
          <button class="btn btn-ghost flex-1 h-10" @click="resetAll()">Rivendos</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function inventorySearch() {
  const url = new URL(window.location.href);
  const getAll = k => url.searchParams.getAll(k);
  const getOne = k => url.searchParams.get(k) ?? '';

  // ----- static helpers kept outside the returned object -----
  function buildSortLabel(state, sortOptions){
    const f = sortOptions.find(s => s.value === state.sort);
    return f ? f.label : 'Rendit';
  }

  return {
    // ---------- reactive state ----------
    state: {
      q: getOne('q'),
      sort: getOne('sort') || 'relevance',
      // facets
      prodhuesi: getAll('prodhuesi'),
      modeli: getAll('modeli'),
      karburanti: getAll('karburanti'),
      transmisioni: getAll('transmisioni'),
      ngjyra: getAll('ngjyra'),
      uleset: getAll('uleset'),
      viti: getAll('viti'),
      // ranges
      min_viti: Number(getOne('min_viti')) || '',
      max_viti: Number(getOne('max_viti')) || '',
      min_cmim: Number(getOne('min_cmim')) || '',
      max_cmim: Number(getOne('max_cmim')) || '',
      min_km:   Number(getOne('min_km'))   || '',
      max_km:   Number(getOne('max_km'))   || '',
    },

    // facet data from PHP (SSR) to keep counts aligned initially
    facets: {
      prodhuesi: @json($facets['prodhuesi'] ?? []),
      modeli: @json($facets['modeli'] ?? []),
      karburanti: @json($facets['karburanti'] ?? []),
      transmisioni: @json($facets['transmisioni'] ?? []),
      ngjyra: @json($facets['ngjyra'] ?? []),
      uleset: @json($facets['uleset'] ?? []),
      // IMPORTANT: map years → viti if your controller uses 'years'
      viti: @json(($facets['viti'] ?? ($facets['years'] ?? []))),
    },

    facetsOrder: [
      {key:'prodhuesi', title:'Marka'},
      {key:'modeli', title:'Modeli'},
      {key:'karburanti', title:'Karburanti'},
      {key:'transmisioni', title:'Transmisioni'},
      {key:'ngjyra', title:'Ngjyra'},
      {key:'uleset', title:'Ulëset'},
      {key:'viti', title:'Viti'},
      {key:'cmimi', title:'Çmimi (€)'},
      {key:'km', title:'Kilometrazhi'},
    ],

    sortOptions: [
      {value:'relevance',   label:'Më të përshtatshmet'},
      {value:'newest',      label:'Më të rejat'},
      {value:'price_asc',   label:'Çmimi — më i ulët'},
      {value:'price_desc',  label:'Çmimi — më i lartë'},
      {value:'km_asc',      label:'Kilometrazhi — më i ulët'},
      {value:'km_desc',     label:'Kilometrazhi — më i lartë'},
      {value:'year_desc',   label:'Viti — më i ri'},
      {value:'year_asc',    label:'Viti — më i vjetër'},
    ],

    // >>> these must be reactive props (not closure vars)
    view: localStorage.getItem('inv_view') || 'grid',
    drawer: false,

    // ---------- methods (use `this.`) ----------
    toQuery(){
      const p = new URLSearchParams();
      if (this.state.q) p.set('q', this.state.q);
      p.set('sort', this.state.sort || 'relevance');
      ['prodhuesi','modeli','karburanti','transmisioni','ngjyra','uleset','viti']
        .forEach(k => (this.state[k] || []).forEach(v => v && p.append(k, v)));
      ['min_viti','max_viti','min_cmim','max_cmim','min_km','max_km']
        .forEach(k => { if (this.state[k] !== '' && this.state[k] !== null) p.set(k, this.state[k]); });
      p.set('view', this.view);
      return p.toString();
    },

    sortLabel(){ return buildSortLabel(this.state, this.sortOptions); },

    showSkeleton(show){ document.getElementById('skeleton')?.classList.toggle('hidden', !show); },

    async apply(push=true){
      this.showSkeleton(true);
      const qs = this.toQuery();
      const suggestUrl = '{{ route('vehicles.suggest') }}' + (qs ? ('?'+qs) : '');
      const fullUrl    = '{{ route('vehicles.search') }}'  + (qs ? ('?'+qs) : '');

      const res = await fetch(suggestUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const html = await res.text();
      this.showSkeleton(false);

      const container = document.getElementById('results');
      container.innerHTML = html;

      const hdr = container.querySelector('[data-header]');
      if (hdr) document.getElementById('resultHeader').innerHTML = hdr.innerHTML;

      container.dataset.view = this.view;

      if (push) window.history.pushState({}, '', fullUrl);
    },

    resetAll(){
      Object.assign(this.state, {
        q:'', sort:'relevance',
        prodhuesi:[], modeli:[], karburanti:[], transmisioni:[], ngjyra:[], uleset:[], viti:[],
        min_viti:'', max_viti:'', min_cmim:'', max_cmim:'', min_km:'', max_km:''
      });
      this.apply();
    },

    toggle(key, val){
      if (key === 'viti' && (val ?? '').toString().length) {
        this.state.viti = this.state.viti || [];
        const i = this.state.viti.indexOf(val);
        if (i >= 0) this.state.viti.splice(i,1); else this.state.viti.push(val);
      } else {
        this.state[key] = this.state[key] || [];
        const i = this.state[key].indexOf(val);
        if (i >= 0) this.state[key].splice(i,1); else this.state[key].push(val);
      }
      this.apply();
    },
    isOn(key, val){ return (this.state[key] || []).includes(val); },

    chips(){
      const out = [];
      const labels = {
        prodhuesi:'Marka', modeli:'Modeli', karburanti:'Karburanti',
        transmisioni:'Transmisioni', ngjyra:'Ngjyra', uleset:'Ulëset', viti:'Viti'
      };
      Object.keys(labels).forEach(k=>{
        (this.state[k]||[]).forEach(v=> out.push({key:k, val:v, label:`${labels[k]}: ${v}`}));
      });
      [['min_viti','Viti ≥'],['max_viti','Viti ≤'],['min_cmim','€ ≥'],['max_cmim','€ ≤'],['min_km','KM ≥'],['max_km','KM ≤']]
        .forEach(([k,lab])=> { if(this.state[k]!=='' && this.state[k]!==null) out.push({key:k,val:this.state[k],label:`${lab} ${this.state[k]}`}); });
      if (this.state.q) out.push({key:'q', val:this.state.q, label:`Kërkim: ${this.state.q}`});
      return out;
    },

    removeChip(chip){
      if (Array.isArray(this.state[chip.key])) this.state[chip.key] = this.state[chip.key].filter(v => v !== chip.val);
      else this.state[chip.key] = '';
      this.apply();
    },

    clearGroup(key){
      if (['cmimi','km'].includes(key)) {
        if (key==='cmimi') { this.state.min_cmim=''; this.state.max_cmim=''; }
        if (key==='km') { this.state.min_km=''; this.state.max_km=''; }
      } else if (key==='viti') {
        this.state.viti=[]; this.state.min_viti=''; this.state.max_viti='';
      } else {
        this.state[key] = [];
      }
      this.apply();
    },

    setView(v){
      this.view = v;
      localStorage.setItem('inv_view', v);
      document.getElementById('results')?.setAttribute('data-view', v);
    },

    openDrawer(){ this.drawer = true; document.documentElement.style.overflow = 'hidden'; },
    closeDrawer(){ this.drawer = false; document.documentElement.style.overflow = ''; },

    measureHeader(){
      const hdr = document.querySelector('header, .site-header, [data-site-header]');
      const h = hdr ? (hdr.getBoundingClientRect().height || 88) : 88;
      document.documentElement.style.setProperty('--header-offset', h + 'px');
    },

    init(){
      const hdr = document.getElementById('results').querySelector('[data-header]');
      if (hdr) document.getElementById('resultHeader').innerHTML = hdr.innerHTML;
      document.getElementById('results')?.setAttribute('data-view', this.view);
      this.measureHeader(); window.addEventListener('resize', () => this.measureHeader(), { passive:true });
    }
  };
}

window.addEventListener('popstate', () => window.location.reload());
</script>


{{-- Small CSS tweak to switch grid/list without another partial --}}
<style>
  /* Base: make every card the same height in grid */
  #results .grid > .card { height: 100%; }
  #results .card > a { display:flex; flex-direction:column; height:100%; }

  /* Media keeps a fixed aspect in grid */
  #results .card > a > .car-media { position:relative; width:100%; padding-bottom:62%; background:#1a2233; }
  #results .card > a > .car-media img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; }

  /* Body area grows, price locks at bottom */
  #results .card > a > .car-body { display:flex; flex:1 1 auto; flex-direction:column; padding:1.0rem 1.25rem 1.1rem; }
  #results .car-title { font-weight:800; color:#fff; line-height:1.2; margin-bottom:.25rem;
                        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  #results .car-sub { color:#9aa3b2; margin-bottom:.5rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  #results .car-badges { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem; }
  #results .car-badges > span { background:#0f141f; color:#d1d5db; border:1px solid #2a3246;
                                border-radius:9999px; padding:.25rem .65rem; font-size:.75rem; }
  #results .car-footer { margin-top:auto; display:flex; align-items:center; justify-content:space-between; }
  #results .car-price { color:#fff; font-weight:800; font-size:1.125rem; }

  /* ========= LIST MODE (compact rows) ========= */
  #results[data-view="list"] .grid { grid-template-columns: 1fr !important; }
  #results[data-view="list"] .card > a {
    display:grid; grid-template-columns: 220px 1fr; gap:16px; align-items:center;
    padding:10px 12px;
  }
  #results[data-view="list"] .card > a > .car-media {
    padding-bottom:0 !important; width:220px; height:140px; border-radius:10px; overflow:hidden;
  }
  #results[data-view="list"] .card > a > .car-body { padding:0; height:140px; }
  #results[data-view="list"] .car-title { -webkit-line-clamp:1; margin-bottom:.15rem; }
  #results[data-view="list"] .car-badges { margin:.35rem 0 .5rem; gap:.4rem; }
  #results[data-view="list"] .car-price { font-size:1rem; }
  #results[data-view="list"] .car-footer span:last-child { font-size:.875rem; }

  /* Prevent jiggle: reserve space even if some fields missing */
  #results .car-sub, #results .car-badges { min-height: 1.25rem; }
  #results[data-view="list"] .car-badges { min-height: 1.6rem; }
</style>
@endsection
