<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with('category')
            ->active()
            ->sorted();

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort options
        switch ($request->get('sort')) {
            case 'popular':
                $query->popular();
                break;
            case 'price_low':
                // This is complex since prices are in JSON, we'll use a basic sort for now
                $query->orderBy('name');
                break;
            case 'price_high':
                $query->orderBy('name', 'desc');
                break;
            default:
                // Default sorting already applied
                break;
        }

        $perPage = min($request->get('per_page', 15), 50);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        if (!$product->is_active) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->load('category');

        return response()->json([
            'product' => $product
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
            'price_options' => ['required', 'array', 'min:1'],
            'price_options.*.value' => ['required', 'numeric', 'min:1'],
            'price_options.*.price' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data = $request->except('image');
        $data['slug'] = Str::slug($request->name);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product = Product::create($data);
        $product->load('category');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
            'price_options' => ['sometimes', 'array', 'min:1'],
            'price_options.*.value' => ['required_with:price_options', 'numeric', 'min:1'],
            'price_options.*.price' => ['required_with:price_options', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data = $request->except('image');

        // Update slug if name changed
        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product->update($data);
        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        // Check if product has any orders
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing orders'
            ], 400);
        }

        // Delete image
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
