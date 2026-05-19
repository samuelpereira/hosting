<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\ClientEmailLog;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientEmailLogAttachmentController extends Controller
{
    public function __invoke(ClientEmailLog $emailLog, int $index): StreamedResponse
    {
        $files = $emailLog->attachmentFiles();
        abort_unless(isset($files[$index]), 404);

        $file = $files[$index];
        $path = $file['path'] ?? '';
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            $file['name'] ?? basename($path),
        );
    }
}
