<?php
// app/Http/Controllers/FavoriteController.php
namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // List current user's favorites
    public function index(Request $request)
    {
        $vehicles = $request->user()
            ->favorites()
            ->latest('favorites.created_at')
            ->paginate(24)
            ->withQueryString();

        return view('favorites.index', compact('vehicles'));
    }

    // Toggle favorite (add if missing, remove if exists)
    public function toggle(Request $request, Vehicle $vehicle)
    {
        $user = $request->user();

        $already = $user->favorites()->where('vehicle_id', $vehicle->id)->exists();

        if ($already) {
            $user->favorites()->detach($vehicle->id);
            $status = 'removed';
        } else {
            $user->favorites()->attach($vehicle->id);
            $status = 'added';
        }

        // AJAX?
        if ($request->wantsJson()) {
            return response()->json([
                'status' => $status,
                'favorited' => !$already,
                'vehicle_id' => $vehicle->id,
            ]);
        }

        // Classic POST fallback
        return back()->with('success', $status === 'added'
            ? 'U shtua te të preferuarat.'
            : 'U hoq nga të preferuarat.');
    }

    // Explicit remove (optional)
    public function destroy(Request $request, Vehicle $vehicle)
    {
        $request->user()->favorites()->detach($vehicle->id);
        return back()->with('success', 'U hoq nga të preferuarat.');
    }
}
