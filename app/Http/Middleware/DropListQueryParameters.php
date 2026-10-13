<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every filter and search box in the app reads its query parameter as one
 * string (?search=, ?from=, ?month=, ?ref= ...). The same name sent as a list
 * (?search[]=x, one edited URL) reached string-typed code and the page
 * answered 500, public pages included. A list where a single value belongs
 * can only be a hand-made URL, so it is dropped here and the page behaves as
 * if the filter was not set.
 *
 * Only the query string of GET and HEAD requests is touched: form and JSON
 * bodies (invoice lines, credit note lines) are lists by design and are
 * validated where they are used. The raw QUERY_STRING is left alone, so
 * signed links verify exactly as before.
 */
class DropListQueryParameters
{
    /**
     * Query parameters the app reads as a list on a GET page. None today:
     * add a name here before a page starts reading one.
     */
    private const LISTS = [];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            foreach ($request->query->all() as $name => $value) {
                if (is_array($value) && ! in_array($name, self::LISTS, true)) {
                    $request->query->remove($name);
                }
            }
        }

        return $next($request);
    }
}
