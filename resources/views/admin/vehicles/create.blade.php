@extends('layouts.app')

@section('content')
    <!-- Additional styles for admin vehicles create page -->
    <style>
        body { font-family: 'Bahnschrift', 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6, .heading { font-family: 'Montserrat', sans-serif; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
    </style>

    <div class="max-w-3xl mx-auto py-24 px-4 sm:px-6 lg:px-8">
        <div class="bg-brand-card border border-gray-800 shadow rounded-lg p-6">
            <h2 class="text-2xl font-bold text-white mb-6">Add New Vehicle</h2>

            <form method="POST" action="{{ route('admin.vehicles.store') }}" enctype="multipart/form-data">
                @csrf

                @if ($errors->any())
                    <div class="bg-red-900/20 border border-red-700 text-red-300 px-4 py-3 rounded mb-6">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300">Vehicle Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="mt-1 block w-full border border-gray-700 bg-brand-black text-white rounded-md shadow-sm focus:ring-brand-red focus:border-brand-red sm:text-sm" required>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300">Description</label>
                        <textarea name="description" id="description" rows="4" class="mt-1 block w-full border border-gray-700 bg-brand-black text-white rounded-md shadow-sm focus:ring-brand-red focus:border-brand-red sm:text-sm" required>{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label for="vehicle_image" class="block text-sm font-medium text-gray-300">Vehicle Image</label>
                        <input type="file" name="vehicle_image" id="vehicle_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-red file:text-white hover:file:bg-brand-red-dark" required>
                        <p class="mt-1 text-sm text-brand-muted">PNG, JPG, GIF up to 2MB</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-8">
                    <a href="{{ route('admin.vehicles.index') }}" class="bg-gray-700 text-gray-200 px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">Cancel</a>
                    <button type="submit" class="bg-brand-red text-white px-4 py-2 rounded-md hover:bg-brand-red-dark transition-colors">Create Vehicle</button>
                </div>
            </form>
        </div>
    </div>
@endsection



