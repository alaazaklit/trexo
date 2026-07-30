<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class InfoController extends Controller
{
    public function phpInfo()
    {
        phpinfo();
        die(); // To stop further script execution
    }
}