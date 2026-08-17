<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class PhoneNormalizationTest extends TestCase
{
    public function test_local_lebanese_numbers_pass_through_unchanged(): void
    {
        foreach (['03123456', '70123456', '71234567', '76123456', '78123456', '79123456', '81123456'] as $local) {
            $this->assertSame($local, User::normalizePhone($local));
        }
    }

    public function test_strips_961_country_code_prefix(): void
    {
        $this->assertSame('71234567', User::normalizePhone('96171234567'));
        $this->assertSame('71234567', User::normalizePhone('+96171234567'));
    }

    public function test_strips_non_digit_formatting(): void
    {
        $this->assertSame('71234567', User::normalizePhone('+961 71 234 567'));
        $this->assertSame('71234567', User::normalizePhone('961-71-234-567'));
    }

    public function test_is_idempotent_on_an_already_normalized_number(): void
    {
        $this->assertSame('71234567', User::normalizePhone(User::normalizePhone('+96171234567')));
    }
}
