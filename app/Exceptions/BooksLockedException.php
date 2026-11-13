<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A write refused because it falls inside the closed (locked) period.
 *
 * Thrown by the services the web pages and the mobile API share, so each door
 * answers the same refusal its own way: a page goes back with the message
 * (under the field when one is named, otherwise as the page's error banner),
 * the app gets a 422 with the message.
 */
class BooksLockedException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(array_filter([
                'message' => $this->getMessage(),
                'errors' => $this->field ? [$this->field => [$this->getMessage()]] : null,
            ]), 422);
        }

        $back = back()->withInput();

        return $this->field
            ? $back->withErrors([$this->field => $this->getMessage()])
            : $back->with('error', $this->getMessage());
    }
}
