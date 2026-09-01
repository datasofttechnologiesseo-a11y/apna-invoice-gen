@props(['states'])

{{--
    Inline "+ New customer" modal for invoice & quotation forms.

    How it works:
    - Hidden by default. Listens for the `open-quick-customer` window event to show.
    - Submits via fetch() to POST /customers/quick-create (JSON endpoint).
    - On 201: dispatches `customer-added` window event with the new customer payload,
      closes itself, and clears the form. Parent forms listen and update their dropdown.
    - On 422: shows inline field errors from the JSON response.
    - On any other error: shows a generic message and keeps the form so the user can retry.

    The modal is fully self-contained - drop it into any form, no shared state.
    Forms wire it up by:
      1. A button with @click="$dispatch('open-quick-customer')"
      2. A @customer-added.window listener that pushes the new customer into their dropdown
--}}
<div x-data="quickCustomerModal()"
     x-show="open"
     x-cloak
     @open-quick-customer.window="
        open = true;
        if ($event.detail && $event.detail.name) {
            // Pre-fill the name field with whatever the user typed in the combobox
            // so they don't retype. Empty/null detail just opens an empty modal.
            form.name = $event.detail.name;
        }
        $nextTick(() => $refs.nameInput?.focus());
     "
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-gray-900/60"></div>

    {{-- Modal --}}
    <div x-show="open"
         role="dialog" aria-modal="true" aria-label="Add a new customer"
         @keydown.escape.window="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl ring-1 ring-gray-200 overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-display font-bold text-gray-900">Quick add customer</h3>
                <p class="text-xs text-gray-500 mt-0.5">Just the basics - you can fill the full address later from Customers.</p>
            </div>
            <button type="button" @click="open = false" class="text-gray-500 hover:text-gray-600 p-1" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form @submit.prevent="submit" class="p-5 space-y-4">
            {{-- Generic error banner --}}
            <div x-show="genericError" x-cloak class="p-3 bg-danger-50 border border-danger-200 text-danger-800 rounded text-sm" x-text="genericError"></div>

            <div>
                <label for="qc-name" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Customer name *</label>
                <input id="qc-name" type="text" x-model="form.name" x-ref="nameInput" required maxlength="255"
                       placeholder="e.g. Acme Consulting LLP"
                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600"
                       :class="errors.name && '!border-danger-400 !ring-danger-400'">
                <p x-show="errors.name" x-cloak class="mt-1 text-xs text-danger-600" x-text="errors.name"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="qc-state" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">State *</label>
                    <select id="qc-state" x-model="form.state_id" required
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600"
                            :class="errors.state_id && '!border-danger-400 !ring-danger-400'">
                        <option value="">- Select state -</option>
                        @foreach ($states as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->gst_code }})</option>
                        @endforeach
                    </select>
                    <p x-show="errors.state_id" x-cloak class="mt-1 text-xs text-danger-600" x-text="errors.state_id"></p>
                </div>
                <div>
                    <label for="qc-gstin" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">GSTIN <span class="text-gray-500 font-normal normal-case">(optional, B2B)</span></label>
                    <input id="qc-gstin" type="text" x-model="form.gstin" maxlength="15"
                           placeholder="27AAACT2727Q1ZW"
                           class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono uppercase focus:border-brand-600 focus:ring-brand-600"
                           :class="errors.gstin && '!border-danger-400 !ring-danger-400'">
                    <p x-show="errors.gstin" x-cloak class="mt-1 text-xs text-danger-600" x-text="errors.gstin"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="qc-phone" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Phone <span class="text-gray-500 font-normal normal-case">(WhatsApp share)</span></label>
                    <input id="qc-phone" type="tel" inputmode="tel" x-model="form.phone" maxlength="30"
                           placeholder="+91 98765 43210"
                           class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600"
                           :class="errors.phone && '!border-danger-400 !ring-danger-400'">
                    <p x-show="errors.phone" x-cloak class="mt-1 text-xs text-danger-600" x-text="errors.phone"></p>
                </div>
                <div>
                    <label for="qc-email" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Email <span class="text-gray-500 font-normal normal-case">(invoice copy)</span></label>
                    <input id="qc-email" type="email" x-model="form.email" maxlength="255"
                           placeholder="billing@customer.com"
                           class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600"
                           :class="errors.email && '!border-danger-400 !ring-danger-400'">
                    <p x-show="errors.email" x-cloak class="mt-1 text-xs text-danger-600" x-text="errors.email"></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 rounded-lg transition">Cancel</button>
                <button type="submit" :disabled="submitting"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-800 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold rounded-lg shadow-sm transition">
                    <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                    <span x-text="submitting ? 'Adding…' : 'Save customer'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function quickCustomerModal() {
                return {
                    open: false,
                    submitting: false,
                    genericError: '',
                    errors: {},
                    form: { name: '', state_id: '', gstin: '', phone: '', email: '' },

                    reset() {
                        this.form = { name: '', state_id: '', gstin: '', phone: '', email: '' };
                        this.errors = {};
                        this.genericError = '';
                    },

                    async submit() {
                        if (this.submitting) return;
                        this.submitting = true;
                        this.errors = {};
                        this.genericError = '';

                        try {
                            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            const res = await fetch('{{ route("customers.quick-create") }}', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(this.form),
                            });

                            if (res.status === 201) {
                                const payload = await res.json();
                                // Notify any listening parent form so it can inject + select.
                                window.dispatchEvent(new CustomEvent('customer-added', { detail: payload }));
                                this.reset();
                                this.open = false;
                                return;
                            }

                            if (res.status === 422) {
                                const body = await res.json();
                                const errs = body.errors || {};
                                const firstErrors = {};
                                for (const [field, messages] of Object.entries(errs)) {
                                    firstErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
                                }
                                this.errors = firstErrors;
                                return;
                            }

                            this.genericError = `Could not save (status ${res.status}). Please try again.`;
                        } catch (e) {
                            this.genericError = 'Network problem - check your connection and try again.';
                        } finally {
                            this.submitting = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
