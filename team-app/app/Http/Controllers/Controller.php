<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // $this->authorize(...) melempar 403 bila Gate/Policy menolak (Petunjuk Teknis 4.2: izin diperiksa di server setiap request).
    use AuthorizesRequests;
}
