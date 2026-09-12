@props([
    'name' => 'search',
    'scope' => 'invoices',
    'placeholder' => 'Search',
    'label' => 'Search',
    'allLabel' => 'See all results for',
    'width' => 'w-full sm:w-96',
])

{{--
    Search box with a suggestions dropdown.

    The plain input this replaces made you guess: type, press Enter, wait for a
    page load, find out you had the wrong spelling. The suggestions turn it into
    a lookup - a few letters and the bill is on screen with its customer, date,
    amount and status, one click from open.

    It is an enhancement, not a replacement. The <input> is still a real field
    in the real GET form, so with JavaScript off (or before Alpine boots) typing
    and pressing Enter filters the list exactly as it always did. Everything the
    dropdown adds hangs off that.
--}}

<div x-data="searchSuggest()"
     data-endpoint="{{ route('search.suggest') }}"
     data-scope="{{ $scope }}"
     x-init="boot($el)"
     @click.outside="close()"
     @keydown.escape.stop="close()"
     class="relative {{ $width }}">

    <input type="text"
           name="{{ $name }}"
           value="{{ request($name) }}"
           placeholder="{{ $placeholder }}"
           aria-label="{{ $label }}"
           autocomplete="off"
           role="combobox"
           aria-autocomplete="list"
           :aria-expanded="open.toString()"
           aria-controls="{{ $name }}-suggestions"
           @input.debounce.180ms="lookup($event.target.value)"
           @focus="if (items.length && q.trim() !== '') open = true"
           @keydown.arrow-down.prevent="move(1)"
           @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter="onEnter($event)"
           class="border-gray-300 rounded-md shadow-sm w-full">

    <div x-show="open" x-cloak
         id="{{ $name }}-suggestions"
         role="listbox"
         aria-label="Search suggestions"
         class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-96 overflow-y-auto">

        <div x-show="loading" class="px-3 py-3 text-xs text-gray-500 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
            Searching&hellip;
        </div>

        <template x-if="!loading && items.length === 0 && q.trim() !== ''">
            <div class="px-3 py-3 text-sm text-gray-500">
                Nothing matches "<span class="font-medium text-gray-700" x-text="q.trim()"></span>".
                <span class="block text-xs mt-0.5">Try part of a customer name, a mobile number, a GSTIN or an amount.</span>
            </div>
        </template>

        <template x-for="(item, i) in items" :key="item._group + '-' + i">
            <div>
                {{-- Group heading, printed once when the group changes. --}}
                <template x-if="isGroupStart(i)">
                    <div class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-wider font-bold text-gray-400 bg-gray-50 border-y border-gray-100"
                         x-text="item._group"></div>
                </template>

                <a :href="item.url"
                   role="option"
                   :aria-selected="(highlight === i).toString()"
                   @mouseenter="highlight = i"
                   class="flex items-baseline gap-2 px-3 py-2 text-sm border-b border-gray-50 last:border-0"
                   :class="highlight === i ? 'bg-brand-50' : 'hover:bg-gray-50'">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium text-gray-900 truncate block" x-text="item.title"></span>
                        <span class="text-xs text-gray-500 truncate block" x-text="item.subtitle"></span>
                    </span>
                    <template x-if="item.badge">
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase tracking-wider shrink-0"
                              :class="toneClass(item.tone)" x-text="item.badge"></span>
                    </template>
                    <span class="text-xs font-semibold text-gray-700 tabular-nums whitespace-nowrap shrink-0" x-text="item.meta"></span>
                </a>
            </div>
        </template>

        {{-- Falls through to the ordinary form submit, so the full result list
             stays a real URL the user can bookmark or send to their CA. --}}
        <button type="submit"
                x-show="q.trim() !== '' && !loading"
                @mouseenter="highlight = items.length"
                class="w-full text-left px-3 py-2 text-xs font-semibold border-t border-gray-100 text-brand-700"
                :class="highlight === items.length ? 'bg-brand-50' : 'hover:bg-gray-50'">
            {{ $allLabel }} "<span x-text="q.trim()"></span>"
        </button>
    </div>
</div>

@once
@push('scripts')
<script>
    /**
     * Alpine component behind the search-suggest Blade component.
     * (Written without the angle-bracket tag name on purpose: Blade
     * compiles a component tag wherever it finds one, comment or not.)
     *
     * Config arrives on the root element's data attributes rather than as call
     * arguments, so this block stays free of Blade and the inline-script syntax
     * test can parse it as ordinary JavaScript.
     */
    function searchSuggest() {
        return {
            endpoint: '',
            scope: 'invoices',
            q: '',
            open: false,
            loading: false,
            items: [],
            highlight: -1,
            controller: null,
            lastTerm: null,

            boot: function (el) {
                this.endpoint = el.dataset.endpoint || '';
                this.scope = el.dataset.scope || 'invoices';
                var input = el.querySelector('input[type="text"]');
                this.q = input ? input.value : '';
            },

            isGroupStart: function (i) {
                return i === 0 || this.items[i - 1]._group !== this.items[i]._group;
            },

            toneClass: function (tone) {
                var map = {
                    paid: 'bg-money-100 text-money-800',
                    due: 'bg-accent-100 text-accent-800',
                    danger: 'bg-danger-100 text-danger-800',
                    muted: 'bg-gray-100 text-gray-600'
                };
                return map[tone] || 'bg-gray-100 text-gray-600';
            },

            lookup: function (value) {
                this.q = value;
                var term = value.trim();

                if (term === '') {
                    this.close();
                    this.items = [];
                    this.lastTerm = null;
                    return;
                }

                if (term === this.lastTerm) {
                    this.open = true;
                    return;
                }
                this.lastTerm = term;

                // Abandon the request in flight. Without this a slow "ra" can
                // land after a fast "rajesh" and refill the list with rows for
                // a query the user has already typed past.
                if (this.controller) {
                    this.controller.abort();
                }
                var controller = new AbortController();
                this.controller = controller;

                this.loading = true;
                this.open = true;
                this.highlight = -1;

                var self = this;
                var url = this.endpoint
                    + '?scope=' + encodeURIComponent(this.scope)
                    + '&q=' + encodeURIComponent(term);

                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('HTTP ' + res.status); }
                        return res.json();
                    })
                    .then(function (data) {
                        var flat = [];
                        (data.groups || []).forEach(function (group) {
                            (group.items || []).forEach(function (item) {
                                item._group = group.label;
                                flat.push(item);
                            });
                        });
                        self.items = flat;
                        self.highlight = -1;
                        self.loading = false;
                    })
                    .catch(function (err) {
                        // An aborted request is the expected case, not a
                        // failure: a newer one is already running and will set
                        // both the rows and the spinner.
                        if (err.name === 'AbortError') { return; }
                        self.items = [];
                        self.loading = false;
                    });
            },

            // Index items.length is the "see all results" row at the bottom.
            move: function (delta) {
                if (this.items.length === 0) { return; }
                this.open = true;
                var total = this.items.length + 1;
                var next = this.highlight + delta;
                if (next < 0) { next = total - 1; }
                if (next >= total) { next = 0; }
                this.highlight = next;
            },

            onEnter: function (event) {
                if (this.open && this.highlight >= 0 && this.highlight < this.items.length) {
                    event.preventDefault();
                    window.location.href = this.items[this.highlight].url;
                    return;
                }
                // Nothing picked, or the "see all" row: let the form submit.
                // Same destination, and the result stays a shareable URL.
                this.close();
            },

            close: function () {
                this.open = false;
                this.highlight = -1;
            }
        };
    }
</script>
@endpush
@endonce
