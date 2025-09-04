<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shoot; // Your Shoot model
use App\Models\Payment; // A new model to log payments
use Square\SquareClient;
use Square\Models\CreateCheckoutRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\Money;
use Square\Models\CreateRefundRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Square\Exceptions\ApiException;

class PaymentController extends Controller
{
    protected $squareClient;

    /**
     * Constructor to initialize the Square Client.
     */
    public function __construct()
    {
        // Set up the Square client with credentials from config
        $this->squareClient = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment'), // 'sandbox' or 'production'
        ]);
    }

    /**
     * Create a Square Checkout link for a specific shoot.
     */
    public function createCheckoutLink(Request $request, Shoot $shoot)
    {
        // Calculate amount to be paid in cents
        $amountToPay = (int) (($shoot->total_quote - $shoot->total_paid) * 100);

        // Prevent creating payment links for fully paid or invalid amount shoots
        if ($amountToPay <= 0) {
            return response()->json(['error' => 'This shoot is already fully paid or has a zero balance.'], 400);
        }

        try {
            // 1. Create an Order
            $money = new Money();
            $money->setAmount($amountToPay);
            $money->setCurrency(config('services.square.currency', 'USD'));

            $lineItem = new OrderLineItem('1');
            $lineItem->setName('Payment for Shoot at ' . $shoot->address);
            $lineItem->setBasePriceMoney($money);
            // Add metadata to link the payment back to your internal models
            $lineItem->setMetadata(['shoot_id' => (string)$shoot->id]);

            $order = new Order(config('services.square.location_id'));
            $order->setLineItems([$lineItem]);
            $order->setReferenceId((string)$shoot->id); // Link order to the shoot

            $createOrderRequest = new CreateOrderRequest();
            $createOrderRequest->setOrder($order);
            $createOrderRequest->setIdempotencyKey(Str::uuid()->toString());

            $orderResponse = $this->squareClient->getOrdersApi()->createOrder($createOrderRequest);
            $createdOrder = $orderResponse->getResult()->getOrder();

            // 2. Create the Checkout Request using the Order
            $checkoutRequest = new CreateCheckoutRequest(
                Str::uuid()->toString(),
                ['order' => $createdOrder]
            );

            // Set a redirect URL for after the payment is completed
            $checkoutRequest->setRedirectUrl(route('shoots.payment.success', ['shoot' => $shoot->id]));
            
            $checkoutResponse = $this->squareClient->getCheckoutApi()->createCheckout(
                config('services.square.location_id'),
                $checkoutRequest
            );

            $checkout = $checkoutResponse->getResult()->getCheckout();

            // Return the checkout URL to the frontend
            return response()->json([
                'checkoutUrl' => $checkout->getCheckoutPageUrl()
            ]);

        } catch (ApiException $e) {
            Log::error("Square API Exception: " . $e->getMessage(), ['response_body' => $e->getResponseBody()]);
            return response()->json(['error' => 'Could not create payment link. Please try again later.'], 500);
        } catch (\Exception $e) {
            Log::error("Generic Exception in createCheckoutLink: " . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Handle incoming webhooks from Square.
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Square webhook received:', $payload);

        // Check if the event type is a payment update
        if (isset($payload['type']) && $payload['type'] === 'payment.updated') {
            $paymentData = $payload['data']['object']['payment'];

            // Only process completed payments
            if ($paymentData['status'] === 'COMPLETED') {
                $orderId = $paymentData['order_id'];
                $paymentId = $paymentData['id'];
                $amount = $paymentData['amount_money']['amount'] / 100; // Convert from cents
                $currency = $paymentData['amount_money']['currency'];

                // Retrieve the order to get the shoot_id from the reference_id
                $orderResponse = $this->squareClient->getOrdersApi()->retrieveOrder($orderId);
                $order = $orderResponse->getResult()->getOrder();
                $shootId = $order->getReferenceId();

                if ($shootId) {
                    $shoot = Shoot::find($shootId);

                    // Prevent duplicate processing
                    if ($shoot && !Payment::where('square_payment_id', $paymentId)->exists()) {
                        // Record the payment in your database
                        $shoot->payments()->create([
                            'amount' => $amount,
                            'currency' => $currency,
                            'square_payment_id' => $paymentId,
                            'square_order_id' => $orderId,
                            'status' => 'completed',
                        ]);

                        // Update the total paid amount on the shoot
                        $shoot->total_paid += $amount;
                        $shoot->save();

                        Log::info("Payment for Shoot ID {$shootId} processed successfully.");
                    }
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Refund a specific payment.
     */
    public function refundPayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|string', // The Square Payment ID
            'amount' => 'required|numeric|min:0.01', // Amount to refund
        ]);

        $paymentId = $request->input('payment_id');
        $amountToRefund = (int) ($request->input('amount') * 100);

        try {
            $money = new Money();
            $money->setAmount($amountToRefund);
            $money->setCurrency(config('services.square.currency', 'USD'));

            $refundRequest = new CreateRefundRequest(
                Str::uuid()->toString(), // Idempotency key
                $paymentId,
                $money
            );
            
            $response = $this->squareClient->getRefundsApi()->refundPayment($refundRequest);
            $refund = $response->getResult()->getRefund();

            if ($refund->getStatus() === 'COMPLETED' || $refund->getStatus() === 'PENDING') {
                // Update your internal payment record to reflect the refund
                $payment = Payment::where('square_payment_id', $paymentId)->first();
                if ($payment) {
                    $payment->status = 'refunded'; // Or 'partially_refunded'
                    $payment->save();

                    // Adjust the total paid on the shoot
                    $shoot = $payment->shoot;
                    $shoot->total_paid -= ($refund->getAmountMoney()->getAmount() / 100);
                    $shoot->save();
                }
                
                Log::info("Refund processed for payment ID: {$paymentId}");
                return response()->json(['status' => 'success', 'refund' => $refund]);
            }

            return response()->json(['error' => 'Refund was not successful.', 'refund_status' => $refund->getStatus()], 400);

        } catch (ApiException $e) {
            Log::error("Square Refund API Exception: " . $e->getMessage(), ['response_body' => $e->getResponseBody()]);
            return response()->json(['error' => 'Failed to process refund.'], 500);
        }
    }
}
