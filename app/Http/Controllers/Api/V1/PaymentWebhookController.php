<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle payment gateway webhook (e.g., Midtrans, Stripe).
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // Validate webhook source (example for Midtrans)
        $this->validateWebhookSource($request);

        $payload = $request->all();

        // Extract payment details from webhook payload
        $transactionId = $payload['transaction_id'] ?? $payload['id'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? $payload['status'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? $payload['status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? 'accept';
        $paymentType = $payload['payment_type'] ?? 'unknown';
        $amount = $payload['gross_amount'] ?? $payload['amount'] ?? 0;

        // Find the order
        $order = Order::where('order_number', $orderId)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
            ], 404);
        }

        // Find or create payment record
        $payment = Payment::firstOrCreate(
            ['transaction_id' => $transactionId],
            [
                'order_id' => $order->id,
                'payment_gateway' => $this->getPaymentGatewayName($request),
                'payment_method' => $paymentType,
                'payment_status' => $transactionStatus,
                'amount' => $amount,
                'gateway_response' => $payload,
            ]
        );

        // Update payment if it already exists
        if (!$payment->wasRecentlyCreated) {
            $payment->update([
                'payment_status' => $transactionStatus,
                'gateway_response' => $payload,
                'completed_at' => now(),
            ]);
        }

        // Handle transaction status
        switch ($transactionStatus) {
            case 'settlement':
            case 'capture':
                if ($fraudStatus === 'accept') {
                    $this->handleSuccessfulPayment($order, $payment);
                } else {
                    $this->handleFraudulentPayment($order, $payment);
                }
                break;

            case 'pending':
                $this->handlePendingPayment($order, $payment);
                break;

            case 'cancel':
            case 'expire':
            case 'failure':
                $this->handleFailedPayment($order, $payment);
                break;

            case 'refund':
            case 'partial_refund':
                $this->handleRefundPayment($order, $payment);
                break;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
        ]);
    }

    /**
     * Validate webhook source for security.
     */
    private function validateWebhookSource(Request $request): void
    {
        // Example validation for Midtrans
        $serverKey = config('midtrans.server_key'); // You would need to install/configure midtrans package
        $signatureKey = $request->header('X-Callback-Signature') ?? $request->header('X-Callback-Token');
        
        // For now, we'll just validate that it's a proper webhook request
        // In production, implement proper signature validation
    }

    /**
     * Get payment gateway name from request.
     */
    private function getPaymentGatewayName(Request $request): string
    {
        // Check for common webhook headers
        $headers = $request->headers->all();
        
        foreach ($headers as $header => $value) {
            $header = strtolower($header);
            $value = is_array($value) ? $value[0] : $value;
            
            if (str_contains($header, 'midtrans') || str_contains($value, 'midtrans')) {
                return 'midtrans';
            }
            
            if (str_contains($header, 'stripe') || str_contains($value, 'stripe')) {
                return 'stripe';
            }
            
            if (str_contains($header, 'paypal') || str_contains($value, 'paypal')) {
                return 'paypal';
            }
        }
        
        // Check payload for gateway indicators
        $payload = $request->all();
        
        if (isset($payload['va_numbers']) || isset($payload['payment_type'])) {
            return 'midtrans';
        }
        
        if (isset($payload['object']) && $payload['object'] === 'event') {
            return 'stripe';
        }
        
        return 'unknown';
    }

    /**
     * Handle successful payment.
     */
    private function handleSuccessfulPayment(Order $order, Payment $payment): void
    {
        DB::beginTransaction();

        try {
            // Update order status
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Activate enrollments
            $order->items->each(function ($item) {
                $enrollment = $item->course->enrollments()
                    ->where('user_id', $item->order->user_id)
                    ->first();

                if ($enrollment) {
                    $enrollment->update([
                        'enrolled_at' => now(),
                    ]);
                }
            });

            // Update course stats
            $order->items->each(function ($item) {
                $course = $item->course;
                $course->increment('total_enrollments');
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log error for monitoring
            \Log::error('Error processing successful payment: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Handle pending payment.
     */
    private function handlePendingPayment(Order $order, Payment $payment): void
    {
        $order->update([
            'status' => 'pending',
        ]);
    }

    /**
     * Handle failed payment.
     */
    private function handleFailedPayment(Order $order, Payment $payment): void
    {
        DB::beginTransaction();

        try {
            // Update order status
            $order->update([
                'status' => 'failed',
            ]);

            // Cancel enrollments
            $order->items->each(function ($item) {
                $enrollment = $item->course->enrollments()
                    ->where('user_id', $item->order->user_id)
                    ->first();

                if ($enrollment) {
                    $enrollment->delete();
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log error for monitoring
            \Log::error('Error processing failed payment: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Handle fraudulent payment.
     */
    private function handleFraudulentPayment(Order $order, Payment $payment): void
    {
        DB::beginTransaction();

        try {
            // Update order status
            $order->update([
                'status' => 'failed',
            ]);

            // Cancel enrollments
            $order->items->each(function ($item) {
                $enrollment = $item->course->enrollments()
                    ->where('user_id', $item->order->user_id)
                    ->first();

                if ($enrollment) {
                    $enrollment->delete();
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log error for monitoring
            \Log::error('Error processing fraudulent payment: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Handle refund payment.
     */
    private function handleRefundPayment(Order $order, Payment $payment): void
    {
        DB::beginTransaction();

        try {
            // Update order status
            $order->update([
                'status' => 'refunded',
            ]);

            // Deactivate enrollments (but don't delete them for record keeping)
            $order->items->each(function ($item) {
                $enrollment = $item->course->enrollments()
                    ->where('user_id', $item->order->user_id)
                    ->first();

                if ($enrollment) {
                    $enrollment->update([
                        'enrolled_at' => null,
                    ]);
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log error for monitoring
            \Log::error('Error processing refund payment: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
    }
}
