<?php

namespace App\DataTables\Lookups;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side list for the platform's name-only lookups (fuel types, vehicle types, …).
 *
 * @template TModel of Model
 */
final class LookupTable
{
    /**
     * @param  Builder<TModel>  $query
     */
    public function __construct(private readonly Builder $query) {}

    public function json(): JsonResponse
    {
        return DataTables::eloquent($this->query->select(['id', 'name', 'created_at', 'updated_at']))
            ->addIndexColumn()
            ->addColumn('created_at_formatted', fn (Model $row) => $row->getAttribute('created_at')?->format('M d, Y'))
            ->addColumn('updated_at_formatted', fn (Model $row) => $row->getAttribute('updated_at')?->format('M d, Y'))
            ->addColumn('action', fn (Model $row) => view('platform.lookups.partials.row-actions', ['row' => $row])->render())
            ->orderColumn('created_at_formatted', 'created_at $1')
            ->orderColumn('updated_at_formatted', 'updated_at $1')
            ->rawColumns(['action'])
            ->make(true);
    }
}
