<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PagHiperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get PagHiper settings
     */
    public function getPagHiper()
    {
        $settings = Setting::getPagHiperSettings();

        return response()->json([
            'settings' => [
                'api_key' => $settings['api_key'] ? '***masked***' : null,
                'token' => $settings['token'] ? '***masked***' : null,
                'environment' => $settings['environment'],
                'is_configured' => PagHiperService::isConfigured(),
            ]
        ]);
    }

    /**
     * Set PagHiper settings
     */
    public function setPagHiper(Request $request)
    {
        $request->validate([
            'api_key' => ['required', 'string'],
            'token' => ['required', 'string'],
            'environment' => ['required', 'in:producao,homologacao'],
        ]);

        try {
            Setting::setPagHiperSettings(
                $request->api_key,
                $request->token,
                $request->environment
            );

            // Log the settings change
            Log::info('PagHiper settings updated', [
                'admin_user_id' => $request->user()->id,
                'admin_email' => $request->user()->email,
                'environment' => $request->environment,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'PagHiper settings updated successfully',
                'settings' => [
                    'api_key' => '***masked***',
                    'token' => '***masked***',
                    'environment' => $request->environment,
                    'is_configured' => true,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update PagHiper settings', [
                'admin_user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update PagHiper settings'
            ], 500);
        }
    }

    /**
     * Test PagHiper credentials
     */
    public function testPagHiper(Request $request)
    {
        if (!PagHiperService::isConfigured()) {
            return response()->json([
                'message' => 'PagHiper is not configured',
                'is_valid' => false,
            ], 400);
        }

        try {
            $pagHiperService = new PagHiperService();
            $isValid = $pagHiperService->testCredentials();

            Log::info('PagHiper credentials tested', [
                'admin_user_id' => $request->user()->id,
                'is_valid' => $isValid,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => $isValid ? 'PagHiper credentials are valid' : 'PagHiper credentials are invalid',
                'is_valid' => $isValid,
            ]);

        } catch (\Exception $e) {
            Log::error('PagHiper credentials test failed', [
                'admin_user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to test PagHiper credentials',
                'is_valid' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
