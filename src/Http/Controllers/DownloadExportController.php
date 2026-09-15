<?php

namespace Akika\LaravelExporter\Http\Controllers;

use Akika\LaravelExporter\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExportController extends Controller
{
    public function __invoke(
        Request $request,
        Export $export
    ): StreamedResponse {
        abort_unless(
            $request->hasValidSignature(),
            403,
            'Invalid or expired download link.'
        );

        abort_unless(
            $export->isCompleted(),
            404,
            'Export is not available.'
        );

        abort_if(
            $export->isExpired(),
            410,
            'Export has expired.'
        );

        abort_unless(
            $export->path
                && Storage::disk($export->disk)
                ->exists($export->path),
            404,
            'Export file does not exist.'
        );

        $this->authorizeDownload(
            request: $request,
            export: $export,
        );

        return Storage::disk($export->disk)
            ->download(
                $export->path,
                $export->filename
            );
    }

    protected function authorizeDownload(
        Request $request,
        Export $export
    ): void {
        abort_unless(
            $export->owner_type !== null
                && $export->owner_id !== null,
            403,
            'This export is not available for user download.'
        );

        $user = $request->user();

        abort_unless(
            $user !== null
                && $export->owner_type
                === $user->getMorphClass()
                && (string) $export->owner_id
                === (string) $user->getKey(),
            403,
            'You are not authorized to download this export.'
        );
    }
}
