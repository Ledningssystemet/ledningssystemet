<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryDocument;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class LibraryDocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', LibraryDocument::class);

        $documentType = $request->input('document_type', 'file');
        $rules = LibraryDocument::validationRules();

        // Adjust validation based on document type
        if ($documentType === 'editor') {
            // For editor documents, filename and contenttype are optional initially
            $rules['filename'] = ['nullable', 'string', 'max:255'];
            $rules['contenttype'] = ['nullable', 'string', 'max:255'];
            $rules['contentlength'] = ['nullable', 'integer', 'min:0'];
            $rules['filecontent'] = ['nullable'];
        }

        $data = $request->validate($rules);

        // Set defaults for editor documents
        if ($documentType === 'editor') {
            $data['filename'] = $data['filename'] ?? $data['name'] . '.doc';
            $data['contenttype'] = 'ledningssystemet/document';
            $data['contentlength'] = 0;
        }

        // Create the document
        $document = new LibraryDocument();
        $document->fill($data);

        // Handle file upload
        if ($request->hasFile('filecontent') && $documentType === 'file') {
            $file = $request->file('filecontent');
            $path = $file->store('documents');

            $document->filename = $file->getClientOriginalName();
            $document->contenttype = $file->getClientMimeType();
            $document->contentlength = $file->getSize();
            $document->filecontent = $path;
        }

        $document->save();

        // Create initial DocumentVersion for editor documents
        if ($documentType === 'editor') {
            DocumentVersion::create([
                'library_document_id' => $document->id,
                'contents' => json_encode(['blocks' => []]), // Empty EditorJS content
                'major_version' => 1,
                'minor_version' => 0,
            ]);
        }

        return response()->json($document->fresh(), Response::HTTP_CREATED);
    }
}

