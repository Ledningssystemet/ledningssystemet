<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LibraryDocumentDownloadController extends Controller
{
    public function show(string $id): Response
    {
        $document = LibraryDocument::query()->findOrFail($id);
        Gate::authorize('view', $document);

        // If filecontent is empty, then throw 404
        if((null == $document->filecontent) || ("" == $document->filecontent))
            abort(404);

        $filename = (string) ($document->filename ?: ('document-' . $document->getKey().'.pdf'));
        $contentType = (string) ($document->contenttype ?: 'application/octet-stream');
        if('ledningssystemet/document' == $contentType)
            $contentType = 'application/pdf';

        return response($document->filecontent, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . addcslashes($filename, "\\\"") . '"',
            'Content-Length' => $document->contentlength,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

