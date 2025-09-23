@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-white">Të preferuarat</h1>
    <div class="text-sm text-brand-muted">{{ number_format($vehicles->total()) }} artikuj</div>
  </div>

  @if($vehicles->count())
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      @foreach($vehicles as $v)
        @include('vehicles._card', ['v' => $v]) {{-- or re-use your _grid item --}}
      @endforeach
    </div>

    <div class="mt-8">
      {{ $vehicles->links() }}
    </div>
  @else
    <div class="text-center py-20 text-brand-muted">Nuk ka ende të preferuara.</div>
  @endif
</div>
@endsection