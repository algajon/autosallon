@extends('layouts.app')

@section('content')
    <!-- Additional styles for login page -->
    <style>
        body {
            font-family: 'Bahnschrift', 'Inter', sans-serif;
        }
        h1, h2, h3, h4, h5, h6, .heading {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
    </style>
    <div class="max-w-md w-full space-y-8 p-8 py-48 mx-auto">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-white heading">KOREANCARS.KS</h2>
            <p class="mt-2 text-sm text-brand-muted">Sign in to your account</p>
        </div>
        
        <form class="mt-8 space-y-6" method="POST" action="{{ route('login') }}">
            @csrf
            
            @if ($errors->any())
                <div class="bg-red-900/20 border border-red-700 text-red-300 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-700 placeholder-gray-500 text-white bg-brand-card rounded-md focus:outline-none focus:ring-brand-red focus:border-brand-red focus:z-10 sm:text-sm" 
                           placeholder="Enter your email"
                           value="{{ old('email') }}">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-700 placeholder-gray-500 text-white bg-brand-card rounded-md focus:outline-none focus:ring-brand-red focus:border-brand-red focus:z-10 sm:text-sm" 
                           placeholder="Enter your password">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember" name="remember" type="checkbox" 
                           class="h-4 w-4 text-brand-red focus:ring-brand-red border-gray-700 bg-brand-card rounded" checked>
                    <label for="remember" class="ml-2 block text-sm text-gray-300">
                        Remember me
                    </label>
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-brand-red hover:bg-brand-red-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-red transition-colors">
                    Sign in
                </button>
            </div>

            <div class="text-center">
                <p class="text-sm text-brand-muted">
                    Don't have an account? 
                    <a href="{{ route('register') }}" class="font-medium text-brand-red hover:text-brand-red-dark">
                        Register here
                    </a>
                </p>
            </div>

            <div class="text-center">
                <a href="{{ route('index') }}" class="text-sm text-gray-400 hover:text-white">
                    ← Back to Home
                </a>
            </div>
        </form>
    </div>
@endsection

