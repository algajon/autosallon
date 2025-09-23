<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\InStock;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use Carbon\Carbon;

class VehicleController extends Controller
{
    /* =========================
     * PUBLIC PAGES
     * ========================= */

    public function publicIndex(): View
    {
        // Stable shuffle for the day (reproducible until midnight)
        $picks = Vehicle::query()
            ->orderByRaw('RAND(UNIX_TIMESTAMP(CURDATE()))')
            ->limit(24)
            ->get();

        // Albanian day label: "20 Shtator"
        $todayLabel = Carbon::now()->locale('sq')->translatedFormat('d F');

        $vehicles = Vehicle::latest()->paginate(24);
        $instocks = InStock::latest()->get();

        return view('index', compact('picks', 'todayLabel', 'vehicles', 'instocks'));
    }

    private const VEHICLE_COLUMNS = [
        'prodhuesi','modeli','varianti','viti',
        'cmimi_eur','kilometrazhi_km','karburanti','ngjyra',
        'transmisioni','uleset','vin','engine_cc',
        'images','listing_url','opsionet','raporti_url',
    ];

    // keep only the real DB columns (drops 'regjistrimi' and any other stray keys)
    private function onlyVehicleColumns(array $data): array
    {
        return array_intersect_key($data, array_flip(self::VEHICLE_COLUMNS));
    }

    // Public detail page
    public function publicShow(Vehicle $vehicle): View
    {
        $images    = $this->imagesToArray($vehicle->images);
        $mainImage = $images[0] ?? 'https://via.placeholder.com/1200x800?text=No+Image';

        // First performance-record URL (if any)
        $reportUrl = null;
        if (!empty($vehicle->raporti_url)) {
            $parts = array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', (string)$vehicle->raporti_url))));
            $reportUrl = $parts[0] ?? null;
        }

        // Options/features (slice for UI)
        $options = [];
        if (!empty($vehicle->opsionet)) {
            $options = array_slice(
                array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', (string)$vehicle->opsionet)))),
                0, 24
            );
        }

        return view('vehicles.show', [
            'vehicle'   => $vehicle,
            'images'    => $images,
            'mainImage' => $mainImage,
            'reportUrl' => $reportUrl,
            'options'   => $options,
        ]);
    }

    /* =========================
     * ADMIN CRUD (resource)
     * ========================= */

    // Admin list
    public function index(): View
    {
        $vehicles = Vehicle::latest()->paginate(20);
        return view('admin.vehicles.index', compact('vehicles'));
    }

    // Create form
    public function create(): View
    {
        return view('admin.vehicles.create');
    }

    // Store new
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data = $this->applyAliases($data);

        // Images from textarea/input
        $data['images'] = $this->parseImages($request->input('images', ''));

        // 🔒 drop unknowns like 'regjistrimi'
        $data = $this->onlyVehicleColumns($data);

        $vehicle = Vehicle::create($data);

        return redirect()
            ->route('admin.vehicles.edit', $vehicle)   // 👈 admin prefix
            ->with('success', 'Automjeti u krijua me sukses.');
    }

    // (Optional) Admin show — redirect to public page
    public function show(Vehicle $vehicle): RedirectResponse
    {
        return redirect()->route('listing.show', $vehicle);
    }

    /* =========================
     * SEARCH (public)
     * ========================= */

    // Full page search (SSR)
    public function search(Request $req)
    {
        [$vehicles, $facets] = $this->runSearch($req);

        // SSR header range (safe)
        $from = $vehicles->total() ? ($vehicles->firstItem() ?? 0) : 0;
        $to   = $vehicles->total() ? ($vehicles->lastItem() ?? 0)  : 0;
        $totalCount = $vehicles->total();

        return view('vehicles.search', [
            'vehicles'   => $vehicles,
            'facets'     => $facets,
            'from'       => $from,
            'to'         => $to,
            'totalCount' => $totalCount,
            'q'          => $req->string('q')->toString(),
        ]);
    }

    // AJAX partial (grid only)
    public function suggest(Request $req)
    {
        // If NOT an AJAX call, show the full search page (keeps Tailwind/layout)
        if (!$req->ajax()) {
            return redirect()->route('vehicles.search', $req->query());
        }

        // AJAX: return only the grid partial for your fetch() to swap in
        [$vehicles, $facets] = $this->runSearch($req);

        return view('vehicles._grid', [
            'vehicles'   => $vehicles,
            'totalCount' => $vehicles->total(),
            // _grid computes its own from/to
        ]);
    }

    /**
     * Core search builder that matches scraper fields.
     * Returns: [LengthAwarePaginator $vehicles, array $facets]
     */
    private function runSearch(Request $req): array
    {
        $q    = trim($req->string('q')->toString());
        $sort = $req->string('sort', 'relevance');

        $builder = Vehicle::query();

        // full-text-ish query across scraper fields (SQ first, EN fallbacks)
        if ($q !== '') {
            $like = "%{$q}%";
            $builder->where(function ($w) use ($q, $like) {
                // SQ fields
                $w->orWhere('prodhuesi',    'like', $like)
                  ->orWhere('modeli',       'like', $like)
                  ->orWhere('varianti',     'like', $like)
                  ->orWhere('vin',          'like', $like)
                  ->orWhere('ngjyra',       'like', $like)
                  ->orWhere('karburanti',   'like', $like)
                  ->orWhere('transmisioni', 'like', $like);

                // EN mirrors — only if the column exists
                foreach (['manufacturer','model','grade','color','fuel','transmission'] as $col) {
                    if ($this->hasCol($col)) {
                        $w->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        // multi-select facets
        foreach (['prodhuesi','modeli','karburanti','transmisioni','ngjyra','uleset','viti'] as $k) {
            $vals = $this->arrify($req->input($k));
            if ($vals) $builder->whereIn($k, $vals);
        }

        // ranges
        if ($req->filled('min_viti')) $builder->where('viti', '>=', (int)$req->input('min_viti'));
        if ($req->filled('max_viti')) $builder->where('viti', '<=', (int)$req->input('max_viti'));
        if ($req->filled('min_cmim')) $builder->where('cmimi_eur', '>=', (int)$req->input('min_cmim'));
        if ($req->filled('max_cmim')) $builder->where('cmimi_eur', '<=', (int)$req->input('max_cmim'));
        if ($req->filled('min_km'))   $builder->where('kilometrazhi_km', '>=', (int)$req->input('min_km'));
        if ($req->filled('max_km'))   $builder->where('kilometrazhi_km', '<=', (int)$req->input('max_km'));

        // sorting
        switch ($sort) {
            case 'newest':     $builder->orderByDesc('id'); break;
            case 'price_asc':  $builder->orderBy('cmimi_eur'); break;
            case 'price_desc': $builder->orderByDesc('cmimi_eur'); break;
            case 'km_asc':     $builder->orderBy('kilometrazhi_km'); break;
            case 'km_desc':    $builder->orderByDesc('kilometrazhi_km'); break;
            case 'year_desc':  $builder->orderByDesc('viti'); break;
            case 'year_asc':   $builder->orderBy('viti'); break;
            default:           $builder->orderByDesc('id'); // relevance fallback
        }

        // facets (counts on the full filtered set; compute BEFORE pagination to avoid page-limited counts)
        $facetsBase = clone $builder;
        // remove columns/orders/limit/offset before grouping to avoid SQL conflicts
        $facetsBase->getQuery()->columns = null;
        $facetsBase->getQuery()->orders  = null;
        $facetsBase->getQuery()->limit   = null;
        $facetsBase->getQuery()->offset  = null;

        $facets = [
            'prodhuesi'    => $this->facet(clone $facetsBase, 'prodhuesi', 12),
            'modeli'       => $this->facet(clone $facetsBase, 'modeli', 12),
            'karburanti'   => $this->facet(clone $facetsBase, 'karburanti', 8),
            'transmisioni' => $this->facet(clone $facetsBase, 'transmisioni', 8),
            'ngjyra'       => $this->facet(clone $facetsBase, 'ngjyra', 10),
            'uleset'       => $this->facet(clone $facetsBase, 'uleset', 6),
            'years'        => $this->facetYears(clone $facetsBase, 8),
        ];

        // Now paginate for results list
        $perPage  = 24;
        $vehicles = (clone $builder)->paginate($perPage)->withQueryString();

        return [$vehicles, $facets];
    }

    /* =========================
     * SUGGEST (live search chips list)
     * ========================= */

    public function suggestOld(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response('');

        $tokens = $this->splitTokens($q);

        $fieldsFT = [
            'prodhuesi','modeli','varianti','vin','ngjyra','karburanti','transmisioni',
            'manufacturer','model','grade','color','fuel','transmission'
        ];

        $query = Vehicle::query();

        $query->where(function($AND) use ($tokens, $fieldsFT) {
            foreach ($tokens as $t) {
                $like  = '%'.$t.'%';
                $likeP = $t.'%';

                $AND->where(function($OR) use ($like, $likeP) {
                    $OR->orWhere('prodhuesi', 'like', $likeP)
                       ->orWhere('modeli',    'like', $like)
                       ->orWhere('varianti',  'like', $like)
                       ->orWhere('vin',       'like', $like)
                       ->orWhere('ngjyra',    'like', $like)
                       ->orWhere('karburanti','like', $like)
                       ->orWhere('transmisioni','like',$like)
                       ->orWhere('manufacturer','like',$likeP)
                       ->orWhere('model',       'like',$like)
                       ->orWhere('grade',       'like',$like)
                       ->orWhere('color',       'like',$like)
                       ->orWhere('fuel',        'like',$like)
                       ->orWhere('transmission','like',$like);
                });

                if (preg_match('/^\d{4}$/', $t)) {
                    $AND->orWhere('viti', $t)->orWhere('year', 'like', '%'.$t.'%');
                }
            }
        });

        $results = $query->orderByDesc('id')->limit(8)->get();

        return view('vehicles._suggest', compact('results','q'));
    }

    /* =========================
     * CRUD helpers
     * ========================= */

    // Accepts both sq/en field names and maps to mirrors when missing
    private function applyAliases(array $data): array
    {
        $aliases = [
            // sq  => en
            'prodhuesi'        => 'manufacturer',
            'modeli'           => 'model',
            'varianti'         => 'grade',
            'viti'             => 'year',
            'kilometrazhi_km'  => 'mileage',
            'karburanti'       => 'fuel',
            'transmisioni'     => 'transmission',
            'ngjyra'           => 'color',
            'uleset'           => 'seats',
        ];

        foreach ($aliases as $sq => $en) {
            if (!array_key_exists($en, $data) && array_key_exists($sq, $data)) {
                $data[$en] = $data[$sq];
            }
        }

        return $data;
    }

    // Validation rules for both store & update
    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            // SQ or EN mirrors are both accepted
            'prodhuesi'        => ['nullable','string','max:100'],
            'manufacturer'     => ['nullable','string','max:100'],

            'modeli'           => ['nullable','string','max:100'],
            'model'            => ['nullable','string','max:100'],

            'varianti'         => ['nullable','string','max:150'],
            'grade'            => ['nullable','string','max:150'],

            'viti'             => ['nullable','string','max:50'],
            'year'             => ['nullable','string','max:50'],

            'cmimi_eur'        => ['nullable','integer','min:0'],
            'price'            => ['nullable','string','max:100'],

            'kilometrazhi_km'  => ['nullable','integer','min:0'],
            'mileage'          => ['nullable','string','max:50'],

            'karburanti'       => ['nullable','string','max:50'],
            'fuel'             => ['nullable','string','max:50'],

            'transmisioni'     => ['nullable','string','max:50'],
            'transmission'     => ['nullable','string','max:50'],

            'ngjyra'           => ['nullable','string','max:50'],
            'color'            => ['nullable','string','max:50'],

            'uleset'           => ['nullable','integer','min:1','max:9'],
            'seats'            => ['nullable','integer','min:1','max:9'],

            'vin'              => ['nullable','string','max:64'],
            'engine_cc'        => ['nullable','integer','min:0','max:10000'],

            'listing_url'      => ['nullable','url','max:2048'],
            'opsionet'         => ['nullable','string','max:5000'],  // semicolon list from scraper
            'raporti_url'      => ['nullable','string','max:5000'],  // semicolon list; first is primary

            // images comes as textarea (we parse ourselves)
            'images'           => ['nullable','string'],
        ]);
    }

    private function toNullableInt($v): ?int
    {
        if ($v === '' || $v === null) return null;
        if (is_numeric($v)) return (int)$v;
        $digits = preg_replace('/\D+/', '', (string)$v);
        return $digits === '' ? null : (int)$digits;
    }

    // Parse images from textarea (accept ; , newline or JSON)
    private function parseImages(?string $input): array
    {
        if ($input === null) return [];

        $input = trim($input);
        if ($input === '') return [];

        if (str_starts_with($input, '[') && str_ends_with($input, ']')) {
            $arr = json_decode($input, true);
            if (is_array($arr)) {
                return array_values(array_filter(array_map('trim', $arr)));
            }
        }

        $parts = preg_split('/[\r\n;,]+/u', $input) ?: [];
        $parts = array_map('trim', $parts);
        $parts = array_values(array_filter($parts));
        return $parts;
    }

    // Convert any images storage to array (model -> view)
    private function imagesToArray($value): array
    {
        if (is_array($value)) return array_values(array_filter($value));
        if (is_string($value) && $value !== '') {
            $v = trim($value);
            if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
                $arr = json_decode($v, true);
                if (is_array($arr)) return array_values(array_filter(array_map('trim', $arr)));
            }
            return array_values(array_filter(array_map('trim', preg_split('/[\r\n;,]+/u', $value) ?: [])));
        }
        return [];
    }

    // For editing convenience (fill a textarea)
    private function imagesToString($value): string
    {
        $arr = $this->imagesToArray($value);
        return implode(';', $arr);
    }

    /* =========================
     * Facets
     * ========================= */

    private function facet($base, string $col, int $limit = 10): array
    {
        return (clone $base)
            ->select($col, DB::raw('COUNT(*) as c'))
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->groupBy($col)
            ->orderByDesc('c')
            ->limit($limit)
            ->get()
            ->map(fn($row)=> ['v'=>$row->{$col}, 'c'=>$row->c])
            ->values()
            ->all();
    }

    private function facetYears($base, int $limit = 10): array
    {
        return (clone $base)
            ->select('viti', DB::raw('COUNT(*) as c'))
            ->whereNotNull('viti')
            ->where('viti','!=','')
            ->groupBy('viti')
            ->orderByDesc('c')
            ->orderByDesc('viti')
            ->limit($limit)
            ->get()
            ->map(fn($row)=> ['v'=>$row->viti, 'c'=>$row->c])
            ->values()
            ->all();
    }

    public function edit(Vehicle $vehicle)
    {
        $imagesString = $this->imagesToString($vehicle->images);
        return view('admin.vehicles.edit', compact('vehicle', 'imagesString'));
    }

    // Update
    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $this->validateData($request, $vehicle->id);
        $data = $this->applyAliases($data);

        if ($request->filled('images')) {
            $data['images'] = $this->parseImages($request->input('images'));
        }

        // 🔒 drop unknowns like 'regjistrimi'
        $data = $this->onlyVehicleColumns($data);

        $vehicle->update($data);

        return redirect()
            ->route('admin.vehicles.edit', $vehicle)  // 👈 admin prefix
            ->with('success', 'Automjeti u përditësua me sukses.');
    }

    // Delete
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        return redirect()
            ->route('admin.vehicles.index')          // 👈 admin prefix
            ->with('success', 'Automjeti u fshi.');
    }

    /* =========================
     * Small internal helpers
     * ========================= */

    // normalize request values to a clean array of strings
    private function arrify($in): array
    {
        if ($in === null || $in === '') return [];
        if (is_array($in)) {
            return array_values(array_filter(array_map(fn($v)=> is_string($v) ? trim($v) : $v, $in), fn($v)=> $v !== '' && $v !== null));
        }
        return [trim((string)$in)];
    }

    private function hasCol(string $col): bool
    {
        static $cache = [];
        return $cache[$col] ??= \Illuminate\Support\Facades\Schema::hasColumn('vehicles', $col);
    }

    // Utility (optional): OR-like across existing columns
    private function orWhereLikeExisting($query, array $cols, string $like): void
    {
        foreach ($cols as $c) {
            if ($this->hasCol($c)) $query->orWhere($c, 'like', $like);
        }
    }

    /** Build boolean-mode query: "audi a4 diesel" -> "+audi* +a4* +diesel*" */
    private function booleanFulltext(string $q): string
    {
        $terms = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_map(fn($t) => '+'.$t.'*', $terms));
    }

    private function splitTokens(string $q): array
    {
        $q = preg_replace('/[^\p{L}\p{N}\.]+/u', ' ', $q);
        $tokens = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $seen = [];
        return array_values(array_filter($tokens, function($t) use (&$seen){
            $k = mb_strtolower($t);
            if (isset($seen[$k])) return false;
            return $seen[$k] = true;
        }));
    }
}
