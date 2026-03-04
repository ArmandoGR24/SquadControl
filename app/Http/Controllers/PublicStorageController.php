<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PublicStorageController extends Controller
{
    public function __invoke(Request $request, string $path)
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

        $absolutePath = $disk->path($normalizedPath);

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            abort(404);
        }

        $size = filesize($absolutePath);

        if ($size === false || $size <= 0) {
            return response()->file($absolutePath);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $fileName = basename($absolutePath);

        $start = 0;
        $end = $size - 1;
        $status = Response::HTTP_OK;

        $rangeHeader = $request->header('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $matches) === 1) {
            $rangeStart = $matches[1] !== '' ? (int) $matches[1] : null;
            $rangeEnd = $matches[2] !== '' ? (int) $matches[2] : null;

            if ($rangeStart === null && $rangeEnd !== null) {
                $rangeStart = max(0, $size - $rangeEnd);
                $rangeEnd = $size - 1;
            }

            if ($rangeStart !== null && $rangeEnd === null) {
                $rangeEnd = $size - 1;
            }

            if ($rangeStart !== null && $rangeEnd !== null) {
                if ($rangeStart > $rangeEnd || $rangeStart >= $size) {
                    return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, [
                        'Content-Range' => "bytes */{$size}",
                    ]);
                }

                $start = max(0, $rangeStart);
                $end = min($size - 1, $rangeEnd);
                $status = Response::HTTP_PARTIAL_CONTENT;
            }
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Cache-Control' => 'public, max-age=31536000',
        ];

        if ($status === Response::HTTP_PARTIAL_CONTENT) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($absolutePath, $start, $end) {
            $handle = fopen($absolutePath, 'rb');
            if ($handle === false) {
                return;
            }

            fseek($handle, $start);

            $bytesToSend = $end - $start + 1;
            $chunkSize = 1024 * 1024;

            while (! feof($handle) && $bytesToSend > 0) {
                $readLength = min($chunkSize, $bytesToSend);
                $buffer = fread($handle, $readLength);

                if ($buffer === false) {
                    break;
                }

                echo $buffer;
                $bytesToSend -= strlen($buffer);

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }

            fclose($handle);
        }, $status, $headers);
    }
}
