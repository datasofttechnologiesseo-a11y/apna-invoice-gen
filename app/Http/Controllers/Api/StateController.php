<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StateResource;
use App\Models\State;

class StateController extends Controller
{
    /** Static lookup list — Indian states + UTs. Cache-friendly. */
    public function index()
    {
        return StateResource::collection(State::orderBy('name')->get());
    }
}
