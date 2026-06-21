<x-layouts.admin title="Log a data breach" subtitle="Record the incident, then track notifications">

    <form method="POST" action="{{ route('admin.breaches.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        @csrf

        @if ($errors->any())
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Title</label>
            <input name="title" value="{{ old('title') }}" required maxlength="255"
                   class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm" placeholder="e.g. Unauthorised access to backup storage">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">What happened</label>
            <textarea name="description" required rows="4"
                      class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm" placeholder="Nature of the breach, how it was discovered, systems involved.">{{ old('description') }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Severity</label>
                <select name="severity" class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm">
                    @foreach (\App\Models\DataBreach::SEVERITIES as $s)
                        <option value="{{ $s }}" @selected(old('severity', 'medium') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm">
                    @foreach (\App\Models\DataBreach::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', 'open') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Discovered at</label>
                <input type="datetime-local" name="discovered_at" value="{{ old('discovered_at', now()->format('Y-m-d\TH:i')) }}" required
                       class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Occurred at <span class="text-slate-400 font-normal">(if known)</span></label>
                <input type="datetime-local" name="occurred_at" value="{{ old('occurred_at') }}"
                       class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Affected users (estimate)</label>
            <input type="number" name="affected_users" value="{{ old('affected_users', 0) }}" min="0" required
                   class="w-40 rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Data categories exposed</label>
            <input name="data_categories" value="{{ old('data_categories') }}" maxlength="2000"
                   class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm" placeholder="e.g. names, emails, phone numbers, GSTINs">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Remediation steps</label>
            <textarea name="remediation" rows="3" maxlength="5000"
                      class="w-full rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm" placeholder="Containment and mitigation actions taken or planned.">{{ old('remediation') }}</textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition">Log breach</button>
            <a href="{{ route('admin.breaches.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</x-layouts.admin>
