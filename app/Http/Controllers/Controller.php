<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected const REGEX_NOMINAL = '/^\d{1,12}(\.\d{1,2})?$/';

    protected function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 20), 100));
    }
}
