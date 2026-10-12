<?php

namespace App\Support;

/**
 * Pages and actions the top search can take you to: type "gstr", "bank" or
 * "add customer" and go straight there. Keywords include the words people
 * actually use for things (bill, udhaar, kharcha, maal), not only our labels.
 * A team member only sees what their role opens.
 */
class AppDestinations
{
    /**
     * @return list<array{label: string, group: string, url: string, keywords: string, quick: bool}>
     */
    public static function forCurrentUser(): array
    {
        // [label, group, route, params, keywords, show when the box is empty]
        $all = [
            ['New invoice', 'Create', 'invoices.create', [], 'new bill sale gst tax invoice create banao', true],
            ['New quotation', 'Create', 'quotations.create', [], 'new quote estimate proforma', false],
            ['New purchase invoice', 'Create', 'purchases.create', [], 'new purchase bill supplier vendor itc', false],
            ['New expense', 'Create', 'finance.expenses.create', [], 'new expense kharcha cost rent salary', false],
            ['New cash memo', 'Create', 'finance.cash-memos.create', [], 'new cash purchase voucher', false],
            ['Add customer', 'Create', 'customers.create', [], 'new customer party client buyer', true],
            ['Add vendor', 'Create', 'vendors.create', [], 'new vendor supplier party', false],
            ['Add item', 'Create', 'products.create', [], 'new product service item hsn sac maal', false],
            ['Upload bank statement', 'Create', 'bank.index', [], 'bank statement reconcile upload', false],
            ['Upload GSTR-2B', 'Create', 'finance.gstr2b', [], 'gstr-2b 2b itc upload match', false],

            ['Dashboard', 'Go to', 'dashboard', [], 'home overview', false],
            ['Invoices', 'Sales', 'invoices.index', [], 'bills sales list invoice', false],
            ['Quotations', 'Sales', 'quotations.index', [], 'quotes estimates proforma', false],
            ['Credit notes', 'Sales', 'reports.show', ['report' => 'credit-notes'], 'credit note sales return refund', false],
            ['Payments received', 'Sales', 'reports.show', ['report' => 'receipts'], 'receipts collections payment received', false],
            ['Purchase invoices', 'Purchases', 'purchases.index', [], 'purchase bills supplier', false],
            ['Payments made', 'Purchases', 'reports.show', ['report' => 'vendor-payments'], 'supplier payments paid', false],
            ['Expenses', 'Purchases', 'finance.expenses', [], 'expenses kharcha costs', false],
            ['Cash memos', 'Purchases', 'finance.cash-memos.index', [], 'cash memo purchase voucher', false],
            ['Customers', 'Parties', 'customers.index', [], 'customers party clients khata ledger', false],
            ['Vendors', 'Parties', 'vendors.index', [], 'vendors suppliers', false],
            ['Items', 'Inventory', 'products.index', [], 'items products services maal hsn sac', false],
            ['Stock summary', 'Inventory', 'reports.show', ['report' => 'stock-summary'], 'stock inventory godown low stock', false],
            ['Bank reconciliation', 'Banking', 'bank.index', [], 'bank reconciliation statement match', false],
            ['GSTR-1', 'GST returns', 'finance.gstr1', [], 'gstr-1 gstr1 outward sales return json portal file', true],
            ['GSTR-2B match', 'GST returns', 'finance.gstr2b', [], 'gstr-2b gstr2b itc purchase match reconcile', false],
            ['GSTR-3B', 'GST returns', 'finance.gstr3b', [], 'gstr-3b gstr3b tax payable challan pmt-06', false],
            ['HSN summary', 'GST returns', 'reports.show', ['report' => 'hsn-summary'], 'hsn sac summary table 12', false],
            ['Profit & Loss', 'Reports', 'finance.index', [], 'profit loss p&l hisaab income', false],
            ['Receivables aging', 'Reports', 'finance.aging', [], 'receivables outstanding dues udhaar udhar collection aging', true],
            ['Payables aging', 'Reports', 'reports.show', ['report' => 'payables-aging'], 'payables dues suppliers aging', false],
            ['All reports', 'Reports', 'reports.index', [], 'reports registers sales register purchase register excel export', false],
            ['Company settings', 'Settings', 'company.edit', [], 'company business gstin pan logo signature bank upi invoice number prefix address', false],
            ['Team', 'Settings', 'team.index', [], 'team staff accountant ca users invite access', false],
            ['Settings', 'Settings', 'settings.index', [], 'settings preferences', false],
            ['Backups', 'Settings', 'backup.index', [], 'backup download data export', false],
            ['Import from Excel', 'Settings', 'imports.show', ['type' => 'customers'], 'import excel upload customers items vendors', false],
            ['Activity log', 'Settings', 'profile.activity', [], 'activity audit log history changes', false],
            ['Refer a friend', 'Settings', 'referrals.index', [], 'refer referral lifetime free', false],
            ['Help', 'Help', 'help', [], 'help how to use guide support faq', true],
            ['Take the tour', 'Help', 'dashboard', ['tour' => 1], 'tour guide walkthrough start', true],
        ];

        $out = [];
        foreach ($all as [$label, $group, $route, $params, $keywords, $quick]) {
            // Billing staff land on Invoices, not the dashboard: the tour starts there.
            if ($label === 'Take the tour' && ! Team::can('dashboard')) {
                $route = 'invoices.index';
            }
            if (! Team::can($route)) {
                continue;
            }
            $out[] = [
                'label' => $label,
                'group' => $group,
                'url' => route($route, $params),
                'keywords' => $keywords,
                'quick' => $quick,
            ];
        }

        return $out;
    }

    /**
     * The section of the Help guide for the page you are on, for the "Help
     * with this page" button.
     */
    public static function helpAnchor(): string
    {
        $map = [
            'dashboard' => 'dashboard',
            'invoices.show' => 'finalize', 'invoices.*' => 'invoice', 'credit-notes.*' => 'finalize',
            'quotations.*' => 'quotations',
            'purchases.*' => 'purchases', 'finance.expenses*' => 'purchases', 'finance.cash-memos.*' => 'purchases', 'vendors.*' => 'purchases',
            'customers.*' => 'customers', 'imports.*' => 'customers',
            'products.*' => 'products',
            'bank.*' => 'bank',
            'finance.gstr*' => 'gst',
            'finance.*' => 'reports', 'reports.*' => 'reports',
            'team.*' => 'team',
            'companies.*' => 'setup', 'company.*' => 'setup', 'settings.*' => 'setup',
        ];
        foreach ($map as $pattern => $anchor) {
            if (request()->routeIs($pattern)) {
                return $anchor;
            }
        }

        return '';
    }
}
