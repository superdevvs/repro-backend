<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Shoot;
use App\Models\Payment;
use App\Mail\AccountCreatedMail;
use App\Mail\ShootScheduledMail;
use App\Mail\ShootUpdatedMail;
use App\Mail\ShootRemovedMail;
use App\Mail\ShootReadyMail;
use App\Mail\PaymentConfirmationMail;
use App\Mail\TermsAcceptedMail;

class MailService
{
    /**
     * Send account created email
     */
    public function sendAccountCreatedEmail(User $user, string $resetLink): bool
    {
        try {
            Mail::to($user->email)->send(new AccountCreatedMail($user, $resetLink));
            
            Log::info('Account created email sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send account created email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send shoot scheduled email
     */
    public function sendShootScheduledEmail(User $user, Shoot $shoot, string $paymentLink): bool
    {
        try {
            $shootData = $this->formatShootData($shoot);
            
            Mail::to($user->email)->send(new ShootScheduledMail($user, $shootData, $paymentLink));
            
            Log::info('Shoot scheduled email sent', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shoot scheduled email', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send shoot updated email
     */
    public function sendShootUpdatedEmail(User $user, Shoot $shoot): bool
    {
        try {
            $shootData = $this->formatShootData($shoot);
            
            Mail::to($user->email)->send(new ShootUpdatedMail($user, $shootData));
            
            Log::info('Shoot updated email sent', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shoot updated email', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send shoot removed email
     */
    public function sendShootRemovedEmail(User $user, Shoot $shoot): bool
    {
        try {
            $shootData = $this->formatShootData($shoot);
            
            Mail::to($user->email)->send(new ShootRemovedMail($user, $shootData));
            
            Log::info('Shoot removed email sent', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shoot removed email', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send shoot ready email
     */
    public function sendShootReadyEmail(User $user, Shoot $shoot): bool
    {
        try {
            $shootData = $this->formatShootData($shoot);
            
            Mail::to($user->email)->send(new ShootReadyMail($user, $shootData));
            
            Log::info('Shoot ready email sent', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shoot ready email', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmationEmail(User $user, Shoot $shoot, Payment $payment): bool
    {
        try {
            $shootData = $this->formatShootData($shoot);
            $paymentData = $this->formatPaymentData($payment);
            
            Mail::to($user->email)->send(new PaymentConfirmationMail($user, $shootData, $paymentData));
            
            Log::info('Payment confirmation email sent', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'payment_id' => $payment->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'user_id' => $user->id,
                'shoot_id' => $shoot->id,
                'payment_id' => $payment->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send terms accepted email
     */
    public function sendTermsAcceptedEmail(User $user): bool
    {
        try {
            Mail::to($user->email)->send(new TermsAcceptedMail($user));
            
            Log::info('Terms accepted email sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send terms accepted email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Format shoot data for email templates
     */
    private function formatShootData(Shoot $shoot): object
    {
        // Create full address from components
        $fullAddress = trim($shoot->address);
        if ($shoot->city) {
            $fullAddress .= ', ' . $shoot->city;
        }
        if ($shoot->state) {
            $fullAddress .= ', ' . $shoot->state;
        }
        if ($shoot->zip) {
            $fullAddress .= ' ' . $shoot->zip;
        }

        return (object) [
            'id' => $shoot->id,
            'location' => $fullAddress ?: 'TBD',
            'date' => $shoot->scheduled_date ? $shoot->scheduled_date->format('M j, Y') : 'TBD',
            'time' => $shoot->time ?? 'TBD',
            'photographer' => $shoot->photographer ? $shoot->photographer->name : 'TBD',
            'notes' => $shoot->notes,
            'status' => $shoot->status,
            'total' => $shoot->base_quote ?? 0,
            'tax' => $shoot->tax_amount ?? 0,
            'tax_rate' => 0, // Calculate if needed
            'grand_total' => $shoot->total_quote ?? 0,
            'packages' => $this->formatPackages($shoot),
            'service_category' => $shoot->service_category ?? 'Standard'
        ];
    }

    /**
     * Format payment data for email templates
     */
    private function formatPaymentData(Payment $payment): object
    {
        return (object) [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'USD',
            'status' => $payment->status,
            'payment_method' => $payment->payment_method ?? 'Card',
            'transaction_id' => $payment->transaction_id,
            'created_at' => $payment->created_at->format('M j, Y g:i A')
        ];
    }

    /**
     * Format packages for email display
     */
    private function formatPackages(Shoot $shoot): array
    {
        $packages = [];
        
        // Get service information if available
        if ($shoot->service) {
            $packages[] = [
                'name' => $shoot->service->name ?? 'Photography Service',
                'price' => $shoot->base_quote ?? 0
            ];
        } else if ($shoot->service_category) {
            // Fallback to service category
            $categoryNames = [
                'P' => 'Photography Package',
                'iGuide' => 'iGuide Virtual Tour',
                'Video' => 'Video Package'
            ];
            
            $packages[] = [
                'name' => $categoryNames[$shoot->service_category] ?? $shoot->service_category,
                'price' => $shoot->base_quote ?? 0
            ];
        }
        
        // Add tax as separate line item if applicable
        if ($shoot->tax_amount && $shoot->tax_amount > 0) {
            $packages[] = [
                'name' => 'Tax',
                'price' => $shoot->tax_amount
            ];
        }
        
        return $packages;
    }

    /**
     * Generate payment link for shoot
     */
    public function generatePaymentLink(Shoot $shoot): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        return "{$frontendUrl}/payment/{$shoot->id}";
    }

    /**
     * Generate password reset link
     */
    public function generatePasswordResetLink(User $user): string
    {
        // This would typically use Laravel's password reset functionality
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        return "{$frontendUrl}/reset-password?email={$user->email}";
    }
}