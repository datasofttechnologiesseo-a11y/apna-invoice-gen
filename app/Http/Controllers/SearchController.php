<?php

namespace App\Http\Controllers;

use App\Services\SearchSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Typeahead behind the search boxes on the invoice and customer lists.
     *
     * The complaint this answers is that the box behaved like a form field and
     * not like a search bar: you typed, guessed, pressed Enter, and waited for
     * a page load to find out whether you had guessed right. Suggestions turn
     * that into a lookup - three letters and the bill is on screen.
     *
     * Matching is deliberately NOT written here. Invoices go through
     * Invoice::scopeSearch and customers through Customer::scopeSearch, the
     * same scopes the list pages filter with, so the dropdown can never offer
     * a row that pressing Enter then fails to find.
     */
    public function suggest(Request $request, SearchSuggestions $search): JsonResponse
    {
        $q = SearchSuggestions::normalise($request->query('q'));
        $scope = $request->query('scope') === 'customers' ? 'customers' : 'invoices';

        if ($q === '') {
            return response()->json(['q' => '', 'groups' => []]);
        }

        // The web dropdown links each row to its page.
        $groups = array_map(fn ($group) => [
            'label' => $group['label'],
            'items' => array_map(fn ($item) => [
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'meta' => $item['meta'],
                'url' => $item['type'] === 'invoice' ? route('invoices.show', $item['id']) : route('customers.ledger', $item['id']),
                'badge' => $item['badge'],
                'tone' => $item['tone'],
            ], $group['items']),
        ], $search->groups($request->user()->ensureCompany(), $q, $scope));

        return response()->json(['q' => $q, 'groups' => $groups]);
    }
}
