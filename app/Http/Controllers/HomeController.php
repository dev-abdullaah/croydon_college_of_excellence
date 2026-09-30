<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(protected CatalogService $catalog) {}

    public function index(): View
    {
        return view('website.pages.home', [
            'courses' => $this->catalog->displayCourses(),
        ]);
    }
}
