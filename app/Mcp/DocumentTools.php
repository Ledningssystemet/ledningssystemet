<?php

namespace App\Mcp;

use App\Models\LibraryDocument;
use App\Models\Objective;
use PhpMcp\Server\Attributes\McpTool;

class DocumentTools
{
    /**
     * List library documents with optional search.
     *
     * @param  string  $search  Optional search term to filter by name, filename or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of library documents (without file content).
     */
    #[McpTool(name: 'list_library_documents')]
    public function listLibraryDocuments(string $search = '', int $limit = 50): array
    {
        $query = LibraryDocument::with(['int_responsible_user:id,name'])
            ->select(['id', 'name', 'filename', 'description', 'contenttype', 'contentlength', 'responsible_user_id', 'updated_at']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('filename', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->latest('updated_at')->limit($limit)->get()->map(fn (LibraryDocument $d) => [
            'id'               => $d->id,
            'name'             => $d->name,
            'filename'         => $d->filename,
            'description'      => $d->description,
            'content_type'     => $d->contenttype,
            'size_bytes'       => $d->contentlength,
            'responsible_user' => $d->int_responsible_user?->name,
            'updated_at'       => $d->updated_at?->toDateString(),
        ])->toArray();
    }

    /**
     * List objectives (quality/business goals) with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of objectives.
     */
    #[McpTool(name: 'list_objectives')]
    public function listObjectives(string $search = '', int $limit = 50): array
    {
        $query = Objective::select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->limit($limit)->get()->toArray();
    }
}

