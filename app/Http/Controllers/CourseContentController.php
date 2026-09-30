<?php

namespace App\Http\Controllers;

use App\Models\CourseDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Secure delivery of the paid course files.
 *
 * The documents live in `course-files/`, which is deliberately *outside*
 * `public/`, so they are not reachable by guessing a URL. Every request is
 * authorised against the purchase that entitles the visitor to the file.
 */
class CourseContentController extends Controller
{
    public function download(Request $request, CourseDocument $document): BinaryFileResponse
    {
        // 404 before 403 so an unowned document id cannot be used to probe
        // which courses exist.
        abort_unless($document->course?->is_active, 404);

        // Belt and braces: the `purchased` middleware has already run, and
        // the policy is the single source of truth it consults.
        $this->authorize('view', $document->course);

        $path = $this->resolvePath($document);

        if ($path === null) {
            Log::error('A purchased course document is missing from course-files/.', [
                'filename' => $document->filename,
                'course_slug' => $document->course->slug,
            ]);

            abort(404, 'That file is currently unavailable. Please contact us.');
        }

        Log::info('Paid course material downloaded.', [
            'user_id' => $request->user()->id,
            'course_slug' => $document->course->slug,
            'filename' => $document->filename,
        ]);

        return response()->download($path, $document->downloadName(), [
            'X-Content-Type-Options' => 'nosniff',
        ], 'attachment');
    }

    /**
     * Resolve a catalogue filename to a real file inside `course-files/`.
     *
     * `basename()` strips any directory component and the realpath must still
     * sit inside the base directory, so a poisoned filename cannot escape.
     */
    protected function resolvePath(CourseDocument $document): ?string
    {
        $base = realpath(base_path('course-files'));

        if ($base === false) {
            return null;
        }

        $path = realpath($base.DIRECTORY_SEPARATOR.basename($document->filename));

        if ($path === false || ! is_file($path)) {
            return null;
        }

        if (! str_starts_with($path, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }
}
