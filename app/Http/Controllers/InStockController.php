<?php

namespace App\Http\Controllers;

use App\Models\InStock;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InStockController extends Controller
{
    public function index(): View
    {
        $items = InStock::latest()->paginate(20);
        return view('admin.instocks.index', compact('items'));
    }

    public function create(): View
    {
        return view('admin.instocks.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // Handle uploaded gallery images
        $images = [];
        if ($request->hasFile('gallery')) {
            foreach ((array)$request->file('gallery') as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('instocks', 'public');
                    $images[] = asset('storage/' . $path);
                }
            }
        }
        // Fallback to URLs if provided (not shown in create form anymore)
        if (empty($images) && $request->filled('images')) {
            $images = $this->parseImages($request->input('images', ''));
        }
        $data['images'] = $images;

        $item = InStock::create($data);
        return redirect()->route('admin.instocks.edit', $item)->with('success', 'Shtuar te NË STOK.');
    }

    public function edit(InStock $instock): View
    {
        $imagesString = $this->imagesToString($instock->images);
        return view('admin.instocks.edit', compact('instock', 'imagesString'));
    }

    public function update(Request $request, InStock $instock): RedirectResponse
    {
        $data = $this->validateData($request, $instock->id);
        if ($request->filled('images')) {
            $data['images'] = $this->parseImages($request->input('images'));
        }
        $instock->update($data);
        return redirect()->route('admin.instocks.edit', $instock)->with('success', 'U përditësua.');
    }

    public function destroy(InStock $instock): RedirectResponse
    {
        $instock->delete();
        return redirect()->route('admin.instocks.index')->with('success', 'U fshi.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'        => ['required','string','max:255'],
            'description' => ['nullable','string','max:5000'],
            'price'       => ['nullable','integer','min:0'],
            'images'      => ['nullable','string'], // optional legacy textarea
            'gallery'     => ['nullable','array'],
            'gallery.*'   => ['file','image','max:5120'], // ~5MB per file
        ]);
    }

    private function parseImages(?string $input): array
    {
        if ($input === null) return [];
        $input = trim($input);
        if ($input === '') return [];
        if (str_starts_with($input, '[') && str_ends_with($input, ']')) {
            $arr = json_decode($input, true);
            if (is_array($arr)) return array_values(array_filter(array_map('trim', $arr)));
        }
        $parts = preg_split('/[\r\n;,]+/u', $input) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts));
    }

    private function imagesToString($value): string
    {
        if (is_array($value)) return implode(';', $value);
        if (is_string($value) && $value !== '') {
            $v = trim($value);
            if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
                $arr = json_decode($v, true);
                if (is_array($arr)) return implode(';', $arr);
            }
            return implode(';', array_values(array_filter(array_map('trim', preg_split('/[\r\n;,]+/u', $value) ?: []))));
        }
        return '';
    }
}
