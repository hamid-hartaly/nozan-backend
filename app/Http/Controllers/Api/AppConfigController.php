<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'sections' => AppSetting::getValue('ui.sections', []),
            'intake_form' => AppSetting::getValue('ui.intake_form', []),
            'hidden_staff_ids' => AppSetting::getValue('staff.hidden_ids', []),
        ]);
    }
}
