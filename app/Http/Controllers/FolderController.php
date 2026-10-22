<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\RenameFolderRequest;
use App\Http\Requests\Documents\StoreFolderRequest;
use App\Models\Folder;
use App\Services\Documents\FolderBrowser;
use App\Services\Documents\FolderService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The company Documents page (company-level folders) and folder create, rename and delete.
 * Entity folders open on the driver, vehicle and trailer pages (FolderBrowser::url), so a
 * refused change goes back to the open folder, not to the referrer (which loses the tab).
 */
class FolderController extends Controller
{
    public function __construct(private FolderService $folders) {}

    public function index(Request $request, FolderBrowser $browser): View
    {
        return view('documents.index', ['browser' => $browser->forCompany($request->integer('folder') ?: null)]);
    }

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $parent = $request->parent();

        try {
            $folder = $this->folders->create($parent, $request->validated('name'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->to(FolderBrowser::url($parent))->withErrors($e->errors())->withInput();
        }

        Toast::success("Folder “{$folder->name}” created.");

        return redirect()->to(FolderBrowser::url($folder));
    }

    public function update(RenameFolderRequest $request, Folder $folder): RedirectResponse
    {
        try {
            $folder = $this->folders->rename($folder, $request->validated('name'));
        } catch (ValidationException $e) {
            return redirect()->to(FolderBrowser::url($folder))->withErrors($e->errors())->withInput();
        }

        Toast::success('Folder renamed.');

        return redirect()->to(FolderBrowser::url($folder));
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        try {
            $this->folders->delete($folder);
        } catch (ValidationException $e) {
            Toast::error($e->validator->errors()->first());

            return redirect()->to(FolderBrowser::url($folder))->withErrors($e->errors());
        }

        Toast::success("Folder “{$folder->name}” deleted.");

        return redirect()->to(FolderBrowser::url($folder->parent));
    }
}
