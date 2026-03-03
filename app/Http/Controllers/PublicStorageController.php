<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicStorageController extends Controller
{
    public function __invoke(string $path)
    {
        $normalizedPath = ltrim(rawurldecode($path), '/');

        if (Str::startsWith($normalizedPath, 'public/')) {
            $normalizedPath = Str::after($normalizedPath, 'public/');
        }

        if ($normalizedPath === '' || str_contains($normalizedPath, '..') || str_contains($normalizedPath, "\0")) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($normalizedPath)) {
            abort(404);
        }

        return $disk->response($normalizedPath);
    }
}
