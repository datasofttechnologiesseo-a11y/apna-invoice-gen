{{--
    A short guided tour of the app: where to create things, how to search,
    what each part of the menu is for, and where help is. Shown once on a new
    person's first visit (dashboard, or Invoices for billing staff) and on
    demand with ?tour=1 (account menu, Help). Steps whose target is not on
    screen are skipped: the side menu on a phone, or a section a team member's
    role does not open.
--}}
@php
    $tourUser = \App\Support\Team::realUser() ?? auth()->user();
    $tourStart = request()->boolean('tour')
        || ($tourUser && $tourUser->guide_completed_at === null && request()->routeIs('dashboard', 'invoices.index'));
    $tourSteps = [
        ['target' => null, 'title' => 'Welcome to Apna Invoice', 'body' => 'A one-minute tour of where everything is. You can take it again any time from the account menu.'],
        ['target' => 'new', 'title' => 'Create anything', 'body' => 'Invoices, quotations, purchases, expenses, customers and items all start here. Alt+N opens a new invoice.'],
        ['target' => 'search', 'title' => 'Search everything', 'body' => 'Find an invoice or a customer, or type what you want to do, like "GSTR-1" or "add customer". Press / to jump here.'],
        ['target' => 'nav-sales', 'title' => 'Sales', 'body' => 'Invoices, quotations, credit notes and payments received.'],
        ['target' => 'nav-purchases', 'can' => 'purchases.index', 'title' => 'Purchases', 'body' => 'Supplier invoices, payments made, expenses and cash memos. They feed your input tax credit.'],
        ['target' => 'nav-gst-returns', 'can' => 'finance.gstr1', 'title' => 'GST returns', 'body' => 'GSTR-1 JSON for the portal, GSTR-2B matching and GSTR-3B, prepared from your books.'],
        ['target' => 'nav-reports', 'can' => 'reports.index', 'title' => 'Reports', 'body' => 'Profit and loss, aging and registers, ready to send to your CA in Excel or PDF.'],
        ['target' => 'company', 'title' => 'Your business', 'body' => 'Switch between businesses here. GSTIN, logo, signature, bank and UPI details are in Company settings.'],
        ['target' => 'help', 'can' => 'help', 'title' => 'Help is always here', 'body' => 'Opens the guide for the page you are on. For anything else, message us on WhatsApp.'],
    ];
    // Only the parts this person's role opens (a team member's tour is shorter).
    $tourSteps = array_values(array_filter($tourSteps, fn ($s) => empty($s['can']) || \App\Support\Team::can($s['can'])));
@endphp
@if ($tourStart)
    <div x-data="appTour(@js($tourSteps), @js(route('guide.complete')), @js(csrf_token()))" x-init="$nextTick(() => setTimeout(() => start(), 350))"
         @resize.window="open && place()" @keydown.escape.window="open && finish()" class="print:hidden">
        <template x-if="open">
            <div class="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-body">
                {{-- Dimmed page with a cut-out around the item being shown: four
                     panels around it (a giant box-shadow does not paint reliably). --}}
                <div x-show="!rect" class="absolute inset-0 bg-gray-900/60"></div>
                <template x-if="rect">
                    <div>
                        <div class="absolute left-0 right-0 top-0 bg-gray-900/60" :style="`height:${Math.max(0, rect.top)}px`"></div>
                        <div class="absolute left-0 right-0 bottom-0 bg-gray-900/60" :style="`top:${rect.top + rect.height}px`"></div>
                        <div class="absolute left-0 bg-gray-900/60" :style="`top:${rect.top}px;height:${rect.height}px;width:${Math.max(0, rect.left)}px`"></div>
                        <div class="absolute right-0 bg-gray-900/60" :style="`top:${rect.top}px;height:${rect.height}px;left:${rect.left + rect.width}px`"></div>
                        <div class="absolute rounded-lg ring-2 ring-white pointer-events-none" :style="`top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`"></div>
                    </div>
                </template>

                <div class="absolute bg-white rounded-xl shadow-2xl ring-1 ring-gray-200 p-5" :style="cardStyle">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[11px] font-semibold text-gray-500" x-text="`Step ${i + 1} of ${list.length}`"></span>
                        <button type="button" @click="finish()" class="text-xs font-semibold text-gray-500 hover:text-gray-800 px-2 py-1 -mr-2 rounded">Skip tour</button>
                    </div>
                    <h2 id="tour-title" class="mt-1 font-display text-lg font-extrabold text-gray-900" x-text="step.title"></h2>
                    <p id="tour-body" class="mt-1.5 text-sm text-gray-600 leading-relaxed" x-text="step.body"></p>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex gap-1" aria-hidden="true">
                            <template x-for="(s, n) in list" :key="n">
                                <span class="h-1.5 rounded-full transition-all" :class="n === i ? 'w-5 bg-brand-600' : 'w-1.5 bg-gray-300'"></span>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-show="i > 0" @click="back()" class="px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Back</button>
                            <button type="button" @click="next()" x-ref="nextBtn" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-brand-700 hover:bg-brand-800"
                                    x-text="i === list.length - 1 ? 'Finish' : 'Next'"></button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @once
        <script>
            function appTour(steps, completeUrl, csrf) {
                const targetOf = sel => document.querySelector('[data-tour="' + sel + '"]');
                const onScreen = sel => {
                    const el = targetOf(sel);
                    if (!el) return false;
                    const r = el.getBoundingClientRect();
                    return r.width > 0 && r.height > 0 && r.right > 0 && r.left < window.innerWidth;
                };
                return {
                    open: false, i: 0, list: [], rect: null, cardStyle: '',
                    get step() { return this.list[this.i] || {}; },
                    start() {
                        this.list = steps.filter(s => !s.target || onScreen(s.target));
                        if (!this.list.length) return;
                        this.i = 0;
                        this.open = true;
                        this.$nextTick(() => { this.place(); this.$refs.nextBtn?.focus(); });
                    },
                    place() {
                        const vw = window.innerWidth, vh = window.innerHeight;
                        const cw = Math.min(340, vw - 24), ch = 200;
                        const s = this.step;
                        if (!s.target) {
                            this.rect = null;
                            this.cardStyle = `width:${cw}px;left:${(vw - cw) / 2}px;top:${Math.max(16, vh / 2 - ch / 2)}px`;
                            return;
                        }
                        const el = targetOf(s.target);
                        el.scrollIntoView({block: 'nearest'});
                        const r = el.getBoundingClientRect(), pad = 6;
                        // The highlight stays inside the window (the side menu starts at its edge).
                        const t = Math.max(2, r.top - pad), l = Math.max(2, r.left - pad);
                        this.rect = {top: t, left: l, width: Math.min(vw - 2, r.right + pad) - l, height: Math.min(vh - 2, r.bottom + pad) - t};
                        let top = r.bottom + 14, left = r.left;
                        if (r.left < 280 && r.height > 60) {
                            // A section of the side menu: the card goes to its right.
                            left = r.right + 18;
                            top = r.top;
                        } else if (r.left > vw / 2) {
                            left = r.right - cw;
                        }
                        if (top + ch > vh) top = Math.max(12, r.top - ch - 14);
                        top = Math.max(12, Math.min(top, vh - ch - 12));
                        left = Math.max(12, Math.min(left, vw - cw - 12));
                        this.cardStyle = `top:${top}px;left:${left}px;width:${cw}px`;
                    },
                    next() {
                        if (this.i < this.list.length - 1) { this.i++; this.$nextTick(() => this.place()); } else { this.finish(); }
                    },
                    back() {
                        if (this.i > 0) { this.i--; this.$nextTick(() => this.place()); }
                    },
                    finish() {
                        this.open = false;
                        fetch(completeUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, credentials: 'same-origin'}).catch(() => {});
                        if (new URLSearchParams(location.search).has('tour')) {
                            history.replaceState(null, '', location.pathname);
                        }
                    },
                };
            }
        </script>
    @endonce
@endif
