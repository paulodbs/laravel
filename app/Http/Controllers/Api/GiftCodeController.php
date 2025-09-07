<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCode;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftCodeController extends Controller
{
    /**
     * Display a listing of gift codes (admin)
     */
    public function index(Request $request)
    {
        $query = GiftCode::with('product')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by value
        if ($request->has('value')) {
            $query->where('value', $request->value);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $giftCodes = $query->paginate($perPage);

        return response()->json($giftCodes);
    }

    /**
     * Upload gift codes via CSV
     */
    public function upload(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'value' => ['required', 'numeric', 'min:1'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $product = Product::findOrFail($request->product_id);

        try {
            $file = $request->file('csv_file');
            $csv = array_map('str_getcsv', file($file->getPathname()));
            
            DB::beginTransaction();
            
            $imported = 0;
            $errors = [];
            
            foreach ($csv as $row) {
                $code = trim($row[0] ?? '');
                
                if (empty($code)) {
                    continue;
                }
                
                // Check if code already exists
                if (GiftCode::where('code', $code)->exists()) {
                    $errors[] = "Code '{$code}' already exists";
                    continue;
                }
                
                GiftCode::create([
                    'product_id' => $product->id,
                    'value' => $request->value,
                    'code' => $code,
                    'status' => 'available',
                ]);
                
                $imported++;
            }
            
            DB::commit();
            
            return response()->json([
                'message' => "Successfully imported {$imported} gift codes",
                'imported' => $imported,
                'errors' => $errors,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to import gift codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified gift code
     */
    public function update(Request $request, GiftCode $giftCode)
    {
        $request->validate([
            'status' => ['sometimes', 'in:available,sold,used'],
            'code' => ['sometimes', 'string', 'unique:gift_codes,code,' . $giftCode->id],
            'value' => ['sometimes', 'numeric', 'min:1'],
        ]);

        $data = $request->only(['status', 'code', 'value']);

        // Handle status changes
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 'used':
                    $data['used_at'] = now();
                    break;
                case 'available':
                    $data['sold_at'] = null;
                    $data['used_at'] = null;
                    $data['order_id'] = null;
                    break;
            }
        }

        $giftCode->update($data);

        return response()->json([
            'message' => 'Gift code updated successfully',
            'gift_code' => $giftCode
        ]);
    }
}
