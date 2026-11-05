{{--
    Cancel-invoice dialog, shared by the invoice page and the invoice list.

    On the invoice page it is filled for $invoice. On the list one dialog
    serves every row: a button with data-cancel-trigger and data-action,
    data-id, data-number and data-warning fills it through openCancelDialog().
    A failed submit (reason too short) comes back with the invoice id in
    old('cancel_invoice_id') and reopens the dialog for that row.

    Usage: @include('invoices.partials.cancel-dialog', ['invoice' => $invoice])
           @include('invoices.partials.cancel-dialog', ['invoice' => null, 'returnTo' => 'list'])
--}}
@php
    $invoice ??= null;
    $returnTo ??= null;
    $warning = $invoice?->gstr1CancelWarning();
@endphp
<dialog id="cancel-invoice-modal"
        data-reopen="{{ $errors->has('cancellation_reason') ? '1' : '' }}"
        data-reopen-id="{{ old('cancel_invoice_id') }}"
        class="rounded-xl shadow-2xl p-0 backdrop:bg-black/40 w-[calc(100vw-1.5rem)] max-w-lg max-h-[calc(100vh-3rem)]">
    <form method="POST" action="{{ $invoice ? route('invoices.cancel', $invoice) : '' }}" data-cancel-form
          class="p-6 space-y-4 max-h-[calc(100vh-3rem)] overflow-y-auto">
        @csrf
        <input type="hidden" name="cancel_invoice_id" value="{{ $invoice?->id ?? old('cancel_invoice_id') }}" data-cancel-id>
        @if ($returnTo)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif
        <div>
            <h2 class="font-display font-bold text-lg text-gray-900">Cancel this invoice?</h2>
            <p class="mt-1 text-sm text-gray-600">
                <strong class="font-mono" data-cancel-number>{{ $invoice?->invoice_number }}</strong> stays on record for GST audit,
                but no further payments can be recorded against it. The number is not reused.
            </p>
        </div>
        <p data-cancel-warning role="note" @if (! $warning) hidden @endif
           class="rounded-md bg-accent-50 ring-1 ring-accent-200 px-3 py-2 text-sm text-accent-900">{{ $warning }}</p>
        <div>
            <label for="cancellation_reason" class="block text-sm font-medium text-gray-700">Reason *</label>
            <textarea name="cancellation_reason" id="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                      aria-describedby="cancellation_reason-error"
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="e.g. Wrong amount, replaced by INV-0145">{{ old('cancellation_reason') }}</textarea>
            <x-input-error :messages="$errors->get('cancellation_reason')" for="cancellation_reason" class="mt-1" />
        </div>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t">
            <button type="button" onclick="document.getElementById('cancel-invoice-modal').close()"
                    class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 transition">Keep invoice</button>
            <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md text-sm font-semibold bg-danger-600 hover:bg-danger-700 text-white">Cancel invoice</button>
        </div>
    </form>
</dialog>
@push('scripts')
<script>
    window.openCancelDialog = function (btn) {
        const dialog = document.getElementById('cancel-invoice-modal');
        const form = dialog.querySelector('[data-cancel-form]');
        const warning = dialog.querySelector('[data-cancel-warning]');
        if (btn) {
            form.action = btn.dataset.action;
            dialog.querySelector('[data-cancel-id]').value = btn.dataset.id;
            dialog.querySelector('[data-cancel-number]').textContent = btn.dataset.number;
            warning.textContent = btn.dataset.warning || '';
            warning.hidden = ! btn.dataset.warning;
        }
        dialog.showModal();
    };
    // A reason that failed validation: reopen the dialog (for that row, on the list).
    document.addEventListener('DOMContentLoaded', () => {
        const dialog = document.getElementById('cancel-invoice-modal');
        if (! dialog || ! dialog.dataset.reopen) {
            return;
        }
        const id = dialog.dataset.reopenId;
        const trigger = id ? document.querySelector(`[data-cancel-trigger][data-id="${id}"]`) : null;
        if (trigger || dialog.querySelector('[data-cancel-form]').getAttribute('action')) {
            window.openCancelDialog(trigger);
        }
    });
</script>
@endpush
