<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chemical;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChemicalDatasheetDownloadController extends Controller
{
    public function show(string $id): Response
    {
        $chemical = Chemical::query()->findOrFail($id);
        Gate::authorize('view', $chemical);

        $content = $chemical->getAttribute('sdbfilecontent');
        if ($content === null || $content === '') {
            abort(404);
        }

        $filename = (string) ($chemical->sdbfilename ?: ('chemical-'.$chemical->getKey().'-safety-datasheet.pdf'));
        $contentType = (string) ($chemical->sdbcontenttype ?: 'application/pdf');

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.addcslashes($filename, "\\\"").'"',
            'Content-Length' => (string) strlen((string) $content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

