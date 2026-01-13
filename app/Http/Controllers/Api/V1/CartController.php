<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display the user's cart.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $request->user()->cart()->with('items.course')->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $cart->load('items.course'),
            'meta' => [
                'total_items' => $cart->total_quantity,
                'total_price' => $cart->total_price,
            ],
        ]);
    }

    /**
     * Add a course to the cart.
     */
    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $course = Course::findOrFail($validated['course_id']);

        // Check if course is already in cart
        $cart = $request->user()->cart()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        if (CartItem::existsInCart($cart->id, $course->id)) {
            return response()->json([
                'message' => 'Course is already in your cart.',
            ], 422);
        }

        // Add to cart
        $cartItem = $cart->items()->create([
            'course_id' => $course->id,
            'price' => $course->effective_price,
        ]);

        // Update cart totals
        $cart->updateTotals();

        return response()->json([
            'message' => 'Course added to cart successfully.',
            'data' => $cartItem->load('course'),
        ], 201);
    }

    /**
     * Remove a course from the cart.
     */
    public function removeFromCart(Request $request, int $itemId): JsonResponse
    {
        $cart = $request->user()->cart;
        
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $cartItem = $cart->items()->findOrFail($itemId);

        $cartItem->delete();

        // Update cart totals
        $cart->updateTotals();

        return response()->json([
            'message' => 'Course removed from cart successfully.',
        ]);
    }

    /**
     * Update cart item quantity.
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        // Cart items are not quantity-based for courses, so we just validate the item exists
        $cart = $request->user()->cart;
        
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $cartItem = $cart->items()->findOrFail($itemId);

        // For courses, we can update the price if needed (e.g., if discount was applied)
        $course = $cartItem->course;
        $cartItem->update([
            'price' => $course->effective_price,
        ]);

        // Update cart totals
        $cart->updateTotals();

        return response()->json([
            'message' => 'Cart item updated successfully.',
            'data' => $cartItem->load('course'),
        ]);
    }

    /**
     * Clear the entire cart.
     */
    public function clearCart(Request $request): JsonResponse
    {
        $cart = $request->user()->cart;
        
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $cart->items()->delete();
        $cart->update([
            'subtotal' => 0,
            'total' => 0,
        ]);

        return response()->json([
            'message' => 'Cart cleared successfully.',
        ]);
    }

    /**
     * Get cart summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $cart = $request->user()->cart;
        
        if (!$cart) {
            return response()->json([
                'data' => [
                    'total_items' => 0,
                    'subtotal' => 0,
                    'total' => 0,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'total_items' => $cart->total_quantity,
                'subtotal' => $cart->subtotal,
                'discount' => $cart->discount,
                'total' => $cart->total,
            ],
        ]);
    }
}
