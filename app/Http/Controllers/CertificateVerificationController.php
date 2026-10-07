<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    /**
     * Public Certificate Verification & Credential Registry.
     *
     * Accessible by employers, academic institutions, and immigration advisers.
     */
    public function show(Request $request, ?string $certificate_number = null): View
    {
        $number = $certificate_number ?? $request->query('number');

        if (! $number) {
            return view('website.pages.certificates.lookup', [
                'searched'    => false,
                'certificate' => null,
            ]);
        }

        $certificate = Certificate::with(['student', 'course', 'issuedBy'])
            ->where('certificate_number', trim($number))
            ->first();

        return view('website.pages.certificates.verify', [
            'certificate' => $certificate,
            'queryNumber' => trim($number),
        ]);
    }
}
