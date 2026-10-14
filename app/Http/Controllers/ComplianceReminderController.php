<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\ComplianceReminderService;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ComplianceReminderController extends Controller
{
    public function __construct(
        private ComplianceReminderService $reminderService,
    ) {}

    public function sendDriverReminder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', 'integer', TenantRule::exists('drivers')],
            'document_type_id' => 'required|integer|exists:document_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Outside the try: another company's driver is a 404, not a logged 500
        $driver = Driver::with('company')->findOrFail($request->integer('driver_id'));
        Gate::authorize('update', $driver);

        try {
            $documentType = DocumentType::query()
                ->where('id', $request->integer('document_type_id'))
                ->where('module', 'driver')
                ->where('status', true)
                ->first();

            if (! $documentType) {
                throw new NotFoundHttpException('Active driver document type not found.');
            }

            $result = $this->reminderService->sendDriverReminder($driver, $documentType);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            Log::error('Failed to send driver compliance reminder.', [
                'driver_id' => $request->input('driver_id'),
                'document_type_id' => $request->input('document_type_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder. Please try again later.',
            ], 500);
        }
    }

    public function sendVehicleReminder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|integer',
            'document_type_id' => 'required|integer|exists:document_types,id',
            'asset_type' => 'required|in:vehicle,trailer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Outside the try: another company's asset is a 404, not a logged 500
        $assetType = $request->string('asset_type')->toString();
        $asset = ($assetType === 'vehicle' ? Vehicle::query() : Trailer::query())->findOrFail($request->integer('asset_id'));
        Gate::authorize('update', $asset);

        try {
            $documentType = DocumentType::query()
                ->where('id', $request->integer('document_type_id'))
                ->where('module', $assetType)
                ->where('status', true)
                ->first();

            if (! $documentType) {
                throw new NotFoundHttpException("Active {$assetType} document type not found.");
            }

            $asset->loadMissing('assetGroups.driver');

            $result = $this->reminderService->sendVehicleReminder($asset, $documentType, $assetType);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            Log::error('Failed to send vehicle compliance reminder.', [
                'asset_type' => $request->input('asset_type'),
                'asset_id' => $request->input('asset_id'),
                'document_type_id' => $request->input('document_type_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder. Please try again later.',
            ], 500);
        }
    }
}
