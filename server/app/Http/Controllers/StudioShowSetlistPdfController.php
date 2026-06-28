<?php

namespace App\Http\Controllers;

use App\Models\Show;
use App\Services\StudioShowService;
use App\Services\StudioShowSetlistPdfService;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioShowSetlistPdfController extends Controller
{
    public function generate(
        Show $show,
        StudioShowSetlistPdfService $setlistPdf,
        StudioShowService $shows,
    ): RedirectResponse {
        $user = auth()->user();
        abort_unless($user !== null && $user->isDirector(), 403);

        $portalShow = $shows->showForPortal($show->id);

        try {
            $setlistPdf->generate($portalShow, $user);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('studio.shows.show', $portalShow)
                ->withFragment('playlist')
                ->with('setlist_pdf_error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('studio.shows.show', $portalShow)
                ->withFragment('playlist')
                ->with('setlist_pdf_error', 'Unable to generate setlist PDF right now.');
        }

        return redirect()
            ->route('studio.shows.show', $portalShow)
            ->withFragment('playlist')
            ->with('setlist_pdf_generated', true);
    }

    public function download(
        Show $show,
        StudioShowSetlistPdfService $setlistPdf,
        StudioShowService $shows,
        CloudStudioMediaStorage $mediaStorage,
    ): StreamedResponse {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $portalShow = $shows->showForPortal($show->id);
        $generation = $setlistPdf->latestForShow($portalShow->id);

        abort_unless($generation !== null, 404);

        if (! $mediaStorage->exists($generation->storage_reference)) {
            abort(404);
        }

        $filename = sprintf(
            '%s-setlist.pdf',
            str($portalShow->name)->slug('_')->limit(60, ''),
        );

        return $mediaStorage->response(
            $generation->storage_reference,
            $filename,
            'application/pdf',
        );
    }
}
