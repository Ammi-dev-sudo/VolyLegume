<?php

namespace App\Http\Controllers;

use App\Enums\IssuePhotoKind;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\CropIssue;
use App\Models\CropIssuePhoto;
use App\Services\PhotoStorage;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CropIssuePhotoController extends Controller
{
    use UsesFarmContext;

    public function store(Request $request, CropIssue $issue, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('view', $issue);
        Gate::authorize('create', CropIssuePhoto::class);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'kind' => ['required', Rule::enum(IssuePhotoKind::class)],
            'caption' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
        ]);

        $path = $photos->store($request->file('photo'), 'issue-photos');

        $issue->photos()->create([
            'farm_id' => $issue->farm_id,
            'photo_path' => $path,
            'kind' => $validated['kind'],
            'caption' => $validated['caption'] ?? null,
            'taken_at' => $validated['taken_at'] ?? now(),
            'uploaded_by' => $request->user()->id,
        ]);

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Photographie ajoutée au suivi du problème.');
    }

    public function destroy(CropIssuePhoto $photo, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('delete', $photo);

        $photos->delete($photo->photo_path);
        $photo->delete();

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Photographie supprimée.');
    }
}
