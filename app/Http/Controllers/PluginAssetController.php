<?php

namespace App\Http\Controllers;

use App\Plugins\PluginRuntime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PluginAssetController extends Controller
{
    public function __invoke(Request $request, PluginRuntime $pluginRuntime, string $plugin, string $path): BinaryFileResponse
    {
        $manifest = $pluginRuntime->manifest($plugin);
        abort_if($manifest === null, 404);

        $assetPath = $manifest->resolvePublicAsset($path);
        abort_if($assetPath === null, 404);

        return response()->file($assetPath, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => File::mimeType($assetPath) ?: 'application/octet-stream',
        ]);
    }
}

