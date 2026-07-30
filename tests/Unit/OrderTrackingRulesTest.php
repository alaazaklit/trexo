<?php

namespace Tests\Unit;

use App\Services\Tracking\OrderTrackingRules;
use PHPUnit\Framework\TestCase;

/**
 * Plain-PHPUnit unit tests (no app/DB boot — see phpunit.xml) for the pure
 * tracking rules that back OrderController's live-tracking endpoints.
 * OrderTrackingRules is deliberately framework-agnostic so these can run
 * without a database, unlike a Feature test hitting the real API.
 */
class OrderTrackingRulesTest extends TestCase
{
    // --- arrivalRadiusKm() -------------------------------------------------

    public function test_precise_accuracy_uses_the_tightest_radius(): void
    {
        $radiusKm = OrderTrackingRules::arrivalRadiusKm(10.0, 20, 75, 50, 120, 180);

        $this->assertSame(0.075, $radiusKm);
    }

    public function test_accuracy_exactly_at_the_accurate_threshold_still_counts_as_accurate(): void
    {
        $radiusKm = OrderTrackingRules::arrivalRadiusKm(20.0, 20, 75, 50, 120, 180);

        $this->assertSame(0.075, $radiusKm);
    }

    public function test_moderate_accuracy_uses_the_middle_radius(): void
    {
        $radiusKm = OrderTrackingRules::arrivalRadiusKm(35.0, 20, 75, 50, 120, 180);

        $this->assertSame(0.12, $radiusKm);
    }

    public function test_poor_accuracy_uses_the_most_forgiving_radius(): void
    {
        $radiusKm = OrderTrackingRules::arrivalRadiusKm(120.0, 20, 75, 50, 120, 180);

        $this->assertSame(0.18, $radiusKm);
    }

    public function test_missing_accuracy_falls_back_to_the_most_forgiving_radius(): void
    {
        $radiusKm = OrderTrackingRules::arrivalRadiusKm(null, 20, 75, 50, 120, 180);

        $this->assertSame(0.18, $radiusKm);
    }

    // --- nextConfirmationState() --------------------------------------------

    public function test_leaving_the_radius_resets_the_count_to_zero(): void
    {
        $state = OrderTrackingRules::nextConfirmationState(false, 2, 3);

        $this->assertSame(0, $state['count']);
        $this->assertFalse($state['shouldTransition']);
    }

    public function test_a_single_ping_inside_the_radius_does_not_transition_yet(): void
    {
        $state = OrderTrackingRules::nextConfirmationState(true, 0, 3);

        $this->assertSame(1, $state['count']);
        $this->assertFalse($state['shouldTransition']);
    }

    public function test_reaching_the_required_count_triggers_the_transition(): void
    {
        $state = OrderTrackingRules::nextConfirmationState(true, 2, 3);

        $this->assertSame(3, $state['count']);
        $this->assertTrue($state['shouldTransition']);
    }

    public function test_a_count_already_past_the_requirement_still_transitions(): void
    {
        // Defensive: shouldn't happen in practice (the count resets to 0 on
        // every transition), but a stray higher count must not get stuck
        // never firing.
        $state = OrderTrackingRules::nextConfirmationState(true, 5, 3);

        $this->assertTrue($state['shouldTransition']);
    }

    // --- isValidTransition() / isDuplicate() --------------------------------

    public function test_on_way_cannot_skip_directly_to_delivered(): void
    {
        $this->assertFalse(OrderTrackingRules::isValidTransition('on_way', 'delivered'));
    }

    public function test_on_way_cannot_skip_directly_to_in_transit(): void
    {
        $this->assertFalse(OrderTrackingRules::isValidTransition('on_way', 'in_transit'));
    }

    public function test_on_way_can_advance_to_picked_up(): void
    {
        $this->assertTrue(OrderTrackingRules::isValidTransition('on_way', 'picked_up'));
    }

    public function test_picked_up_can_advance_to_in_transit_or_delivered(): void
    {
        $this->assertTrue(OrderTrackingRules::isValidTransition('picked_up', 'in_transit'));
        $this->assertTrue(OrderTrackingRules::isValidTransition('picked_up', 'delivered'));
    }

    public function test_cancellation_is_allowed_from_every_active_status(): void
    {
        foreach (['waiting_driver_response', 'on_way', 'picked_up', 'in_transit', 'driver_rejected', 'request_expired', 'failed_delivery'] as $status) {
            $this->assertTrue(
                OrderTrackingRules::isValidTransition($status, 'canceled'),
                "Expected cancellation to be allowed from {$status}"
            );
        }
    }

    public function test_delivered_and_canceled_are_terminal(): void
    {
        $this->assertFalse(OrderTrackingRules::isValidTransition('delivered', 'canceled'));
        $this->assertFalse(OrderTrackingRules::isValidTransition('canceled', 'on_way'));
    }

    public function test_same_status_is_treated_as_a_valid_no_op_transition(): void
    {
        $this->assertTrue(OrderTrackingRules::isValidTransition('on_way', 'on_way'));
    }

    public function test_same_status_is_flagged_as_a_duplicate(): void
    {
        $this->assertTrue(OrderTrackingRules::isDuplicate('picked_up', 'picked_up'));
        $this->assertFalse(OrderTrackingRules::isDuplicate('picked_up', 'delivered'));
    }

    // --- inactivityLevel() --------------------------------------------------

    public function test_recent_update_is_not_flagged(): void
    {
        $this->assertNull(OrderTrackingRules::inactivityLevel(30, 120, 300));
    }

    public function test_crossing_the_warning_threshold_is_flagged_as_warning(): void
    {
        $this->assertSame('warning', OrderTrackingRules::inactivityLevel(120, 120, 300));
        $this->assertSame('warning', OrderTrackingRules::inactivityLevel(200, 120, 300));
    }

    public function test_crossing_the_stale_threshold_is_flagged_as_stale(): void
    {
        $this->assertSame('stale', OrderTrackingRules::inactivityLevel(300, 120, 300));
        $this->assertSame('stale', OrderTrackingRules::inactivityLevel(600, 120, 300));
    }

    // --- isDuplicatePing() ---------------------------------------------------

    public function test_a_newer_reading_is_not_a_duplicate(): void
    {
        $this->assertFalse(OrderTrackingRules::isDuplicatePing(2000, 1000));
    }

    public function test_an_older_or_equal_reading_is_a_duplicate(): void
    {
        $this->assertTrue(OrderTrackingRules::isDuplicatePing(1000, 1000));
        $this->assertTrue(OrderTrackingRules::isDuplicatePing(500, 1000));
    }

    public function test_a_reading_with_no_timestamp_is_never_treated_as_a_duplicate(): void
    {
        // Older client builds may not send captured_at at all — must not be
        // rejected just because there's nothing to compare.
        $this->assertFalse(OrderTrackingRules::isDuplicatePing(null, 1000));
        $this->assertFalse(OrderTrackingRules::isDuplicatePing(1000, null));
    }
}
