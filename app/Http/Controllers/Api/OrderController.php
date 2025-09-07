<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders (admin)
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product'])
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Filter by customer
        if ($request->has('customer_email')) {
            $query->where('customer_email', 'like', '%' . $request->customer_email . '%');
        }

        $perPage = min($request->get('per_page', 15), 50);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.value' => ['required', 'numeric', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:pix,boleto'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            $orderItems = [];

            // Validate items and calculate total
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if (!$product->is_active) {
                    throw new \Exception("Product {$product->name} is not available");
                }

                $price = $product->getPriceForValue($item['value']);
                if (!$price) {
                    throw new \Exception("Invalid value {$item['value']} for product {$product->name}");
                }

                // Check if enough gift codes are available
                $availableCodes = $product->getAvailableCodesForValue($item['value'], $item['quantity']);
                if ($availableCodes->count() < $item['quantity']) {
                    throw new \Exception("Not enough {$item['value']} gift codes available for {$product->name}");
                }

                $itemTotal = $price * $item['quantity'];
                $totalAmount += $itemTotal;

                $orderItems[] = [
                    'product' => $product,
                    'value' => $item['value'],
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'total_price' => $itemTotal,
                    'available_codes' => $availableCodes,
                ];
            }

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'status' => 'pending',
            ]);

            // Create order items
            foreach ($orderItems as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product']->id,
                    'gift_card_value' => $itemData['value'],
                    'price' => $itemData['price'],
                    'quantity' => $itemData['quantity'],
                    'total_price' => $itemData['total_price'],
                ]);

                // Note: Gift codes will be assigned when payment is confirmed
            }

            DB::commit();

            $order->load(['orderItems.product']);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Order $order)
    {
        // Check if user can access this order
        if (!request()->user()->isAdmin() && $order->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load(['orderItems.product', 'paymentTransactions']);

        return response()->json(['order' => $order]);
    }

    /**
     * Get orders for a specific user
     */
    public function userOrders(Request $request, $userId)
    {
        // Check if user can access these orders
        if (!$request->user()->isAdmin() && $userId != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Order::where('user_id', $userId)
            ->with(['orderItems.product'])
            ->latest();

        $perPage = min($request->get('per_page', 15), 50);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Update the specified order (admin only)
     */
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => ['sometimes', 'in:pending,paid,delivered,cancelled'],
        ]);

        $oldStatus = $order->status;
        
        if ($request->has('status')) {
            $order->status = $request->status;
            
            // Handle status changes
            switch ($request->status) {
                case 'paid':
                    if ($oldStatus !== 'paid') {
                        $order->markAsPaid();
                        // Assign gift codes
                        $this->assignGiftCodesToOrder($order);
                    }
                    break;
                    
                case 'delivered':
                    $order->markAsDelivered();
                    break;
                    
                case 'cancelled':
                    if (in_array($oldStatus, ['paid', 'delivered'])) {
                        return response()->json([
                            'message' => 'Cannot cancel a paid or delivered order'
                        ], 400);
                    }
                    break;
            }
        }

        $order->save();
        $order->load(['orderItems.product', 'paymentTransactions']);

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order
        ]);
    }

    /**
     * Assign gift codes to order when payment is confirmed
     */
    private function assignGiftCodesToOrder(Order $order)
    {
        foreach ($order->orderItems as $orderItem) {
            if (!$orderItem->hasAllCodesAssigned()) {
                $availableCodes = $orderItem->product->getAvailableCodesForValue(
                    $orderItem->gift_card_value, 
                    $orderItem->quantity
                );

                if ($availableCodes->count() >= $orderItem->quantity) {
                    $orderItem->assignGiftCodes($availableCodes);
                }
            }
        }
    }
}
