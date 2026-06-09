<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\ComplianceReminderService;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ComplianceReminderController extends Controller
{
    use CompanyFilterTrait;

    public function __construct(
        private ComplianceReminderService $reminderService,
    ) {}

    public function sendDriverReminder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|integer|exists:drivers,id',
            'document_type_id' => 'required|integer|exists:document_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $driver = Driver::with('company')->findOrFail($request->integer('driver_id'));
            $this->authorizeCompanyAccess($driver, 'You do not have permission to send reminders for this driver.');

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
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder: '.$e->getMessage(),
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

        try {
            $assetType = $request->string('asset_type')->toString();
            $assetId = $request->integer('asset_id');

            if ($assetType === 'vehicle') {
                $asset = Vehicle::query()->findOrFail($assetId);
                $this->authorizeCompanyAccess($asset, 'You do not have permission to access this vehicle.');
            } else {
                $asset = Trailer::query()->findOrFail($assetId);
                $this->authorizeCompanyAccess($asset, 'You do not have permission to access this trailer.');
            }

            $documentType = DocumentType::query()
                ->where('id', $request->integer('document_type_id'))
                ->where('module', $assetType)
                ->where('status', true)
                ->first();

            if (! $documentType) {
                throw new NotFoundHttpException("Active {$assetType} document type not found.");
            }

            $asset->loadMissing('assetGroups.driver');

            if ($asset->assetGroups?->driver && ! $this->userHasAccess($asset->assetGroups->driver)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned driver does not belong to your company.',
                ], 403);
            }

            $result = $this->reminderService->sendVehicleReminder($asset, $documentType, $assetType);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder: '.$e->getMessage(),
            ], 500);
        }
    }
}
