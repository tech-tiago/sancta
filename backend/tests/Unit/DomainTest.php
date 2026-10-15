<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\LiveChannel;
use App\Models\Plan;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    public function test_coupon_never_discounts_below_one_real(): void
    {
        $coupon = new Coupon(['type' => 'fixed', 'value' => 50000]);
        $this->assertSame(1890, $coupon->discountFor(1990));

        $percent = new Coupon(['type' => 'percent', 'value' => 150]);
        $this->assertSame(1890, $percent->discountFor(1990));
    }

    public function test_plan_period_end(): void
    {
        $monthly = new Plan(['interval' => 'month', 'interval_count' => 6, 'price_cents' => 8990]);
        $start = Carbon::parse('2026-01-31');

        $this->assertSame('2026-07-31', $monthly->periodEndFrom($start)->toDateString());
        $this->assertSame(1498, $monthly->monthlyEquivalentCents());
    }

    public function test_role_hierarchy(): void
    {
        $this->assertTrue(UserRole::SuperAdmin->atLeast(UserRole::Admin));
        $this->assertTrue(UserRole::Editor->atLeast(UserRole::Moderator));
        $this->assertFalse(UserRole::Moderator->atLeast(UserRole::Editor));
        $this->assertFalse(UserRole::User->isStaff());
    }

    public function test_youtube_video_id_extraction(): void
    {
        $this->assertSame('NuJ2gazIEs8', (new LiveChannel(['youtube_url' => 'https://www.youtube.com/watch?v=NuJ2gazIEs8']))->youtubeVideoId());
        $this->assertSame('4CAmwaFJo6k', (new LiveChannel(['youtube_url' => 'https://youtu.be/4CAmwaFJo6k']))->youtubeVideoId());
        $this->assertNull((new LiveChannel(['youtube_url' => 'https://www.youtube.com/playlist?list=PLx']))->youtubeVideoId());
    }
}
