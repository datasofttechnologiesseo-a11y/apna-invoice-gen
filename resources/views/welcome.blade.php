<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — GST Invoicing by DST</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-gray-900">

<header class="relative">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-5 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <x-brand-logo class="h-16 md:h-20 w-auto" />
        </a>
        <nav class="flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-md bg-brand-700 text-white hover:bg-brand-800 transition shadow-sm">Go to dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-gray-700 hover:text-brand-700">Log in</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-brand-700 text-white hover:bg-brand-800 transition shadow-sm">Get started free</a>
            @endauth
        </nav>
    </div>
</header>

<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-brand-50 via-white to-accent-50 opacity-70 -z-10"></div>
    <div class="max-w-7xl mx-auto px-6 lg:px-8 pt-16 pb-24 grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent-100 text-accent-700 text-xs font-semibold tracking-wide uppercase">
                <span class="w-2 h-2 rounded-full bg-accent-500"></span> Free while in beta
            </span>
            <h1 class="mt-4 text-4xl md:text-5xl font-extrabold tracking-tight text-gray-900 leading-tight">
                GST-ready invoicing, <span class="text-brand-700">done in under a minute</span>.
            </h1>
            <p class="mt-5 text-lg text-gray-600 max-w-xl">
                Create formal tax invoices with auto CGST/SGST or IGST, HSN/SAC codes, letterhead, and
                one-click PDF. Built for Indian businesses, free to use while we grow.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                    Create your first invoice →
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 hover:border-brand-700 hover:text-brand-700 text-gray-700 font-medium rounded-lg transition">
                    I already have an account
                </a>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-4 max-w-lg text-sm">
                <div class="flex items-center gap-2 text-gray-600"><svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>No credit card</div>
                <div class="flex items-center gap-2 text-gray-600"><svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>Unlimited invoices</div>
                <div class="flex items-center gap-2 text-gray-600"><svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>Print & PDF export</div>
            </div>
        </div>

        <div class="relative">
            <div class="absolute -inset-4 bg-gradient-to-br from-brand-200 to-accent-200 blur-3xl opacity-40 rounded-3xl -z-10"></div>
            <div class="bg-white rounded-2xl shadow-brand ring-1 ring-gray-100 p-6 md:p-8">
                <div class="flex items-center justify-between pb-4 border-b">
                    <div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Invoice</div>
                        <div class="font-bold text-xl">INV-0042</div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Paid</span>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-mono">₹1,25,000.00</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">CGST 9%</span><span class="font-mono">₹11,250.00</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">SGST 9%</span><span class="font-mono">₹11,250.00</span></div>
                    <div class="flex justify-between pt-3 border-t text-lg"><span class="font-semibold">Total</span><span class="font-mono font-bold text-brand-700">₹1,47,500</span></div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button class="px-3 py-2 text-sm bg-brand-700 text-white rounded-md">Download PDF</button>
                    <button class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md">Send to customer</button>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="max-w-3xl">
            <h2 class="text-3xl font-bold text-gray-900">Everything an Indian business needs</h2>
            <p class="mt-3 text-gray-600">No spreadsheets, no manual tax math. Auto-detects inter-state vs intra-state and computes CGST, SGST, or IGST for you.</p>
        </div>

        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach ([
                ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'GST auto-calc', 'body' => 'CGST+SGST for intra-state, IGST for inter-state — detected from customer address.'],
                ['icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'title' => 'Formal PDF invoices', 'body' => 'Your letterhead, logo, signature, HSN/SAC, amount in words — in one click.'],
                ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Draft → Final workflow', 'body' => 'Edit drafts freely. Finalize to lock the number and make it legally issued.'],
                ['icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'title' => 'Partial payments', 'body' => 'Record payments in parts, see outstanding balances at a glance.'],
                ['icon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z', 'title' => 'Multi-currency', 'body' => 'Invoice in INR, USD, EUR, GBP, AED and more with exchange-rate capture.'],
                ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'title' => 'Customer book', 'body' => 'Reuse customer details across invoices. GSTIN remembered per contact.'],
            ] as $f)
                <div class="bg-white rounded-xl p-6 ring-1 ring-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-11 h-11 rounded-lg bg-brand-50 flex items-center justify-center text-brand-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">{{ $f['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20">
    <div class="max-w-5xl mx-auto px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold">Ready in 3 steps</h2>
        <div class="mt-10 grid md:grid-cols-3 gap-8">
            @foreach ([
                ['n' => 1, 't' => 'Sign up', 'b' => 'Create a free account with just your email.'],
                ['n' => 2, 't' => 'Add your company', 'b' => 'Upload logo, set GSTIN, customize invoice prefix.'],
                ['n' => 3, 't' => 'Bill your customer', 'b' => 'Create invoice, download PDF, get paid.'],
            ] as $s)
                <div class="relative">
                    <div class="mx-auto w-12 h-12 rounded-full bg-gradient-to-br from-brand-600 to-brand-800 text-white font-bold text-xl flex items-center justify-center shadow-brand">{{ $s['n'] }}</div>
                    <h3 class="mt-4 font-semibold text-gray-900">{{ $s['t'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $s['b'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-12">
            <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                Start creating invoices →
            </a>
        </div>
    </div>
</section>

<footer class="bg-gray-900 text-gray-300">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-12 grid md:grid-cols-3 gap-8">
        <div>
            <div class="bg-white rounded-lg p-3 inline-block">
                <x-brand-logo class="h-14 w-auto" />
            </div>
            <p class="mt-4 text-sm text-gray-400 max-w-xs">
                A product by <span class="text-white font-semibold">Datasoft Technologies</span> — building
                practical software for modern Indian businesses.
            </p>
        </div>
        <div>
            <h4 class="text-white font-semibold">Product</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('register') }}" class="hover:text-white">Sign up</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">Log in</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-semibold">Legal</h4>
            <ul class="mt-3 space-y-2 text-sm text-gray-400">
                <li>© {{ now()->year }} Datasoft Technologies</li>
                <li>All trademarks are property of their owners</li>
            </ul>
        </div>
    </div>
</footer>

</body>
</html>
