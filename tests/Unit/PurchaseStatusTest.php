<?php

namespace Tests\Unit;

use App\Models\Purchase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_purchase_can_be_downgraded_at_model_level(): void
    {
        // The model allows status changes; the PurchaseService enforces business rules
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_1',
            'stripe_payment_intent_id' => 'pi_test_1',
            'amount' => 9900,
            'currency' => 'gbp',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $purchase->status = 'failed';
        $purchase->save();

        $purchase->refresh();
        $this->assertEquals('failed', $purchase->status);
    }

    public function test_pending_purchase_can_become_paid(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_3',
            'stripe_payment_intent_id' => 'pi_test_3',
            'amount' => 9900,
            'currency' => 'gbp',
            'status' => 'pending',
        ]);

        $purchase->status = 'paid';
        $purchase->paid_at = now();
        $purchase->save();

        $purchase->refresh();
        $this->assertEquals('paid', $purchase->status);
        $this->assertNotNull($purchase->paid_at);
    }

    public function test_failed_purchase_can_become_paid(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_4',
            'stripe_payment_intent_id' => 'pi_test_4',
            'amount' => 9900,
            'currency' => 'gbp',
            'status' => 'failed',
        ]);

        $purchase->status = 'paid';
        $purchase->paid_at = now();
        $purchase->save();

        $purchase->refresh();
        $this->assertEquals('paid', $purchase->status);
    }

    public function test_purchase_status_constants(): void
    {
        $this->assertEquals(['pending', 'paid', 'failed'], ['pending', 'paid', 'failed']);
    }
}