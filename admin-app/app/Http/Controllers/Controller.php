<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // $this->authorize(...) throws 403 when a Gate/Policy refuses (Technical Guide 4.2: permissions are checked on the server on every request).
    use AuthorizesRequests;
}
