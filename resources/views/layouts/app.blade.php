<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

     <title>@yield('title', config('app.name'))</title>
  <!-- optional -->
<meta name="description" content="@yield('meta_description', 'Cars for sale')">
<meta property="og:title" content="@yield('og_title', trim(View::getSection('title') ?? config('app.name')))">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Scripts -->
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-black': '#0a0a0a',
                        'brand-dark': '#1a1a1a',
                        'brand-card': '#2a2a2a',
                        'brand-muted': '#6b7280',
                        'brand-red': '#dc2626',
                        'brand-red-dark': '#b91c1c',
                    },
                    fontFamily: {
                        'auto': ['Inter', 'sans-serif'],
                        'porsche': ['Montserrat', 'sans-serif'],
                        'bahnschrift': ['Bahnschrift', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Bahnschrift', 'Inter', sans-serif;
        }
        
        .nav-link {
            position: relative;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: none;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #dc2626;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .header-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-brand-black text-white font-auto">
    @include('components.header')

    <main>
        @yield('content')
    </main>

    @include('components.footer')
</body>
</html>

