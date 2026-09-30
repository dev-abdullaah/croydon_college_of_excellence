<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\View\View;

/**
 * The course catalogue on a page of its own.
 *
 * Public, because it is a selling page: the homepage already links to it and
 * so does the account page, and neither should require an account to reach.
 *
 * Prices and content come from CatalogService, which is the same source the
 * homepage reads, so there is only ever one definition of a course. Nothing
 * here is taken from the request.
 */
class CourseCatalogController extends Controller
{
    public function __construct(protected CatalogService $catalog) {}

    public function index(): View
    {
        return view('website.pages.courses.index', [
            'courses' => $this->catalog->displayCourses(),
        ]);
    }
}
