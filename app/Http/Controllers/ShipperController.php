<?php

namespace App\Http\Controllers;

use App\DataTables\Operations\ShipperTable;
use App\Http\Requests\Operations\ShipperRequest;
use App\Models\Shipper;
use App\Services\Operations\ShipperService;
use App\Support\Ui\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Saved shippers (req 4.4): list, add, edit, activate/deactivate and delete. Route model binding
 * goes through the company scope, so another company's shipper is a 404.
 */
class ShipperController extends Controller
{
    public function __construct(private ShipperService $shippers) {}

    public function index(): View
    {
        return view('shippers.index');
    }

    public function data(Request $request): JsonResponse
    {
        return (new ShipperTable($request))->json();
    }

    public function create(): View
    {
        return view('shippers.form', ['shipper' => new Shipper(['country_code' => 'US'])]);
    }

    public function store(ShipperRequest $request): RedirectResponse
    {
        $shipper = $this->shippers->create($request->validated());

        Toast::success("Shipper {$shipper->name} added.");

        return redirect()->route('shippers.index');
    }

    public function edit(Shipper $shipper): View
    {
        return view('shippers.form', compact('shipper'));
    }

    public function update(ShipperRequest $request, Shipper $shipper): RedirectResponse
    {
        $this->shippers->update($shipper, $request->validated());

        Toast::success("Shipper {$shipper->name} saved.");

        return redirect()->route('shippers.index');
    }

    public function activate(Shipper $shipper): RedirectResponse
    {
        $this->shippers->setActive($shipper, true);

        Toast::success("{$shipper->name} is active again.");

        return back();
    }

    public function deactivate(Shipper $shipper): RedirectResponse
    {
        $this->shippers->setActive($shipper, false);

        Toast::success("{$shipper->name} is inactive. Trips that use it keep it.");

        return back();
    }

    public function destroy(Shipper $shipper): RedirectResponse
    {
        $this->shippers->delete($shipper);

        Toast::success("Shipper {$shipper->name} deleted.");

        return redirect()->route('shippers.index');
    }
}
