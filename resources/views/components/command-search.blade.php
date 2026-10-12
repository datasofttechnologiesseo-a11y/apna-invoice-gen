@props(['action', 'placeholder', 'noun', 'value' => ''])
{{--
    The top search: one box for everything. Typing shows the pages and actions
    that match ("gstr", "bank", "add customer") and the matching invoices and
    customers. Enter on a highlighted result goes there; Enter with nothing
    highlighted searches the list you are on, as before. "/" focuses it.
--}}
<form method="GET" action="{{ $action }}" class="flex-1 max-w-md" role="search" data-tour="search"
      x-data="commandSearch(@js(\App\Support\AppDestinations::forCurrentUser()), @js(route('search.suggest')))"
      @click.outside="open = false" @keydown.escape.window="open = false">
    <label for="global-search" class="sr-only">Search {{ $noun }}, pages and actions</label>
    <div class="relative">
        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        <input id="global-search" name="search" type="search" x-ref="globalSearch" autocomplete="off" value="{{ $value }}"
               x-model="q" @input="search()" @focus="open = true" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
               @keydown.enter="go($event)"
               placeholder="{{ $placeholder }}" aria-label="Search {{ $noun }}, pages and actions"
               role="combobox" aria-autocomplete="list" aria-controls="global-search-results" :aria-expanded="(open && flat.length > 0).toString()"
               :aria-activedescendant="active >= 0 ? 'gs-opt-' + active : null"
               class="w-full h-9 pl-8 pr-10 text-sm rounded-md border-gray-300 bg-gray-50 focus:bg-white focus:border-brand-500 focus:ring-brand-500/30">
        <kbd class="hidden sm:block absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-mono text-gray-500 border border-gray-300 rounded px-1">/</kbd>

        <div x-show="open && flat.length > 0" x-cloak x-transition.opacity.duration.100ms
             id="global-search-results" role="listbox" aria-label="Search results"
             class="absolute left-0 mt-1.5 w-[min(30rem,calc(100vw-1.5rem))] max-h-[70vh] overflow-y-auto rounded-lg bg-white shadow-xl ring-1 ring-gray-200 py-1.5 z-50">
            <template x-for="section in sections" :key="section.label">
                <div class="py-1">
                    <div class="px-3 pt-1 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500" x-text="section.label"></div>
                    <template x-for="item in section.items" :key="item.key">
                        <a :href="item.url" :id="'gs-opt-' + item.index" role="option" :aria-selected="(active === item.index).toString()"
                           @mouseenter="active = item.index"
                           :class="active === item.index ? 'bg-brand-50' : ''"
                           class="flex items-center gap-3 px-3 py-2 text-sm text-gray-800 hover:bg-brand-50">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium truncate" x-text="item.title"></span>
                                <span x-show="item.subtitle" class="block text-xs text-gray-500 truncate" x-text="item.subtitle"></span>
                            </span>
                            <span x-show="item.meta" class="shrink-0 text-xs font-mono text-gray-600" x-text="item.meta"></span>
                        </a>
                    </template>
                </div>
            </template>
            <div class="px-3 pt-1.5 mt-1 border-t border-gray-100 text-[11px] text-gray-500" x-show="q.trim() !== ''">
                Press Enter to search {{ $noun }} for “<span x-text="q.trim()"></span>”.
            </div>
        </div>
    </div>
</form>

@once
    <script>
        // Global search: pages and actions matched here, records from /search/suggest.
        function commandSearch(destinations, suggestUrl) {
            return {
                q: '', open: false, active: -1, remote: [], timer: null,
                get sections() {
                    const q = this.q.trim().toLowerCase();
                    const out = [];
                    let index = 0;
                    const add = (label, items) => {
                        if (!items.length) return;
                        out.push({label, items: items.map(it => ({...it, index: index++, key: label + it.url}))});
                    };
                    if (q === '') {
                        add('Suggested', destinations.filter(d => d.quick).map(d => ({title: d.label, subtitle: d.group, url: d.url})));
                        return out;
                    }
                    const words = q.split(/\s+/);
                    const matches = destinations
                        .map(d => {
                            const label = d.label.toLowerCase();
                            const hay = label + ' ' + d.keywords + ' ' + d.group.toLowerCase();
                            if (!words.every(w => hay.includes(w))) return null;
                            return {d, score: label.startsWith(q) ? 0 : (label.includes(q) ? 1 : 2)};
                        })
                        .filter(Boolean)
                        .sort((a, b) => a.score - b.score)
                        .slice(0, 7)
                        .map(m => ({title: m.d.label, subtitle: m.d.group, url: m.d.url}));
                    add('Pages and actions', matches);
                    this.remote.forEach(g => add(g.label, g.items.map(it => ({title: it.title, subtitle: it.subtitle, meta: it.meta, url: it.url}))));
                    return out;
                },
                get flat() { return this.sections.flatMap(s => s.items); },
                search() {
                    this.open = true;
                    this.active = -1;
                    clearTimeout(this.timer);
                    const q = this.q.trim();
                    if (q.length < 2) { this.remote = []; return; }
                    this.timer = setTimeout(() => {
                        fetch(suggestUrl + '?scope=invoices&q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
                            .then(r => r.ok ? r.json() : {groups: []})
                            .then(data => { if (this.q.trim() === q) this.remote = data.groups || []; })
                            .catch(() => { this.remote = []; });
                    }, 180);
                },
                move(step) {
                    if (!this.open) { this.open = true; }
                    const n = this.flat.length;
                    if (!n) return;
                    this.active = (this.active + step + n) % n;
                    document.getElementById('gs-opt-' + this.active)?.scrollIntoView({block: 'nearest'});
                },
                go(event) {
                    const item = this.flat[this.active];
                    if (this.open && item) {
                        event.preventDefault();
                        window.location = item.url;
                    }
                },
            };
        }
    </script>
@endonce
