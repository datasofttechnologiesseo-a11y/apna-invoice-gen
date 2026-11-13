<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App-wide search suggestions: the same rows as the web search boxes
 * (SearchSuggestions). Each item says what it opens: an invoice by id, or a
 * customer's ledger by customer id.
 */
class SearchController extends Controller
{
    public function suggest(Request $request, SearchSuggestions $search): JsonResponse
    {
        $q = SearchSuggestions::normalise($request->query('q'));
        $scope = $request->query('scope') === 'customers' ? 'customers' : 'invoices';

        return response()->json([
            'q' => $q,
            'scope' => $scope,
            'groups' => $q === '' ? [] : $search->groups($request->user()->ensureCompany(), $q, $scope),
        ]);
    }
}
