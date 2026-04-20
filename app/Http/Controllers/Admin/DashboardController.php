<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $now = Carbon::now();
        $weekAgo = $now->copy()->subDays(7);
        $monthStart = $now->copy()->startOfMonth();

        $stats = [
            'users' => [
                'total' => User::count(),
                'new_week' => User::where('created_at', '>=', $weekAgo)->count(),
                'new_month' => User::where('created_at', '>=', $monthStart)->count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
            ],
            'companies' => [
                'total' => Company::count(),
                'new_week' => Company::where('created_at', '>=', $weekAgo)->count(),
                'onboarded' => Company::whereNotNull('onboarded_at')->count(),
            ],
            'customers' => [
                'total' => Customer::count(),
                'new_week' => Customer::where('created_at', '>=', $weekAgo)->count(),
            ],
            'invoices' => [
                'total' => Invoice::count(),
                'drafts' => Invoice::where('status', 'draft')->count(),
                'finalized' => Invoice::whereIn('status', ['final', 'partially_paid', 'paid'])->count(),
                'paid' => Invoice::where('status', 'paid')->count(),
                'cancelled' => Invoice::where('status', 'cancelled')->count(),
                'new_week' => Invoice::where('created_at', '>=', $weekAgo)->count(),
                'new_month' => Invoice::where('created_at', '>=', $monthStart)->count(),
            ],
            'revenue' => [
                'grand_total_all_time' => (float) Invoice::whereIn('status', ['final', 'partially_paid', 'paid'])->sum('grand_total'),
                'collected_all_time' => (float) Invoice::sum('paid_amount'),
                'outstanding' => (float) Invoice::whereIn('status', ['final', 'partially_paid'])->sum('balance'),
                'this_month_billed' => (float) Invoice::whereIn('status', ['final', 'partially_paid', 'paid'])
                    ->where('finalized_at', '>=', $monthStart)->sum('grand_total'),
            ],
        ];

        // Daily invoice trend (last 30 days)
        $trendRows = Invoice::query()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $day,
                'label' => Carbon::parse($day)->format('d M'),
                'count' => (int) ($trendRows[$day]->c ?? 0),
            ];
        }

        $topUsers = User::query()
            ->leftJoin('invoices', 'invoices.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(invoices.id) as invoices_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status IN ("final","partially_paid","paid") THEN invoices.grand_total ELSE 0 END), 0) as revenue')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('invoices_count')
            ->limit(10)
            ->get();

        $recentUsers = User::latest('created_at')->take(10)->get();

        $gstRateUsage = DB::table('invoice_items')
            ->selectRaw('gst_rate, COUNT(*) as line_count')
            ->groupBy('gst_rate')
            ->orderByDesc('line_count')
            ->limit(12)
            ->get();

        return view('admin.dashboard', compact('stats', 'trend', 'topUsers', 'recentUsers', 'gstRateUsage'));
    }

    public function users(Request $request): View
    {
        $users = User::query()
            ->withCount(['companies', 'customers', 'invoices'])
            ->selectSub(function ($q) {
                $q->from('invoices')
                    ->whereColumn('invoices.user_id', 'users.id')
                    ->whereIn('status', ['final', 'partially_paid', 'paid'])
                    ->selectRaw('COALESCE(SUM(grand_total), 0)');
            }, 'revenue')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('users.name', 'like', "%{$s}%")->orWhere('users.email', 'like', "%{$s}%");
            }))
            ->orderByDesc('users.created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users', compact('users'));
    }

    public function userDetail(User $user): View
    {
        $user->load([
            'companies' => fn ($q) => $q->withCount(['customers', 'invoices']),
        ]);

        $recentInvoices = Invoice::where('user_id', $user->id)
            ->with(['customer', 'company'])
            ->latest('id')
            ->take(20)
            ->get();

        $totals = [
            'invoices' => Invoice::where('user_id', $user->id)->count(),
            'customers' => Customer::where('user_id', $user->id)->count(),
            'companies' => $user->companies->count(),
            'revenue' => (float) Invoice::where('user_id', $user->id)
                ->whereIn('status', ['final', 'partially_paid', 'paid'])
                ->sum('grand_total'),
        ];

        return view('admin.user-detail', compact('user', 'recentInvoices', 'totals'));
    }

    public function invoices(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['user', 'company', 'customer'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('invoice_number', 'like', "%{$s}%");
            }))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.invoices', compact('invoices'));
    }

    public function companies(Request $request): View
    {
        $companies = Company::query()
            ->with(['user', 'state'])
            ->withCount(['customers', 'invoices'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('gstin', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.companies', compact('companies'));
    }

    public function customers(Request $request): View
    {
        $customers = Customer::query()
            ->with(['user', 'company', 'state'])
            ->withCount('invoices')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('gstin', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.customers', compact('customers'));
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        // Guarded twice: middleware gate AND re-checked here in case of route change.
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 403, 'Cannot impersonate another super admin.');
        abort_if($user->id === $request->user()->id, 422, 'Cannot impersonate yourself.');

        $originalId = $request->user()->id;
        Auth::login($user);
        $request->session()->put('impersonator_id', $originalId);

        return redirect()->route('dashboard')
            ->with('status', "Now viewing the app as {$user->name}.");
    }

    public function stopImpersonate(Request $request): RedirectResponse
    {
        $originalId = $request->session()->get('impersonator_id');
        abort_unless($originalId, 404);

        $original = User::find($originalId);
        abort_unless($original && $original->isSuperAdmin(), 403);

        Auth::login($original);
        $request->session()->forget('impersonator_id');

        return redirect()->route('admin.users')->with('status', 'Returned to your super-admin account.');
    }
}
