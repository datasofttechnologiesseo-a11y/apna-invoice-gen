<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidGstin;
use PHPUnit\Framework\TestCase;

class ValidGstinTest extends TestCase
{
    public function test_round_trip_any_valid_prefix(): void
    {
        // For a few realistic 14-char prefixes, verify the algorithm finds
        // exactly one valid checksum char and then accepts the result.
        $prefixes = ['24ABCDE1234F1Z', '07AAACB2894G1Z', '33AAAAA0000A1Z'];
        $base36 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        foreach ($prefixes as $prefix) {
            $valid = [];
            for ($c = 0; $c < 36; $c++) {
                if (ValidGstin::hasValidChecksum($prefix . $base36[$c])) {
                    $valid[] = $base36[$c];
                }
            }
            $this->assertCount(1, $valid, "Exactly one valid checksum char should exist for prefix {$prefix}");
            $this->assertTrue(ValidGstin::hasValidChecksum($prefix . $valid[0]));
        }
    }

    public function test_rejects_tampered_checksum(): void
    {
        // Pick any prefix, find the one valid check char, then flip it to
        // an invalid one and confirm rejection.
        $prefix = '24ABCDE1234F1Z';
        $base36 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $valid = null;
        for ($c = 0; $c < 36; $c++) {
            if (ValidGstin::hasValidChecksum($prefix . $base36[$c])) {
                $valid = $base36[$c];
                break;
            }
        }
        $this->assertNotNull($valid);
        // Any other char should fail.
        for ($c = 0; $c < 36; $c++) {
            if ($base36[$c] === $valid) continue;
            $this->assertFalse(
                ValidGstin::hasValidChecksum($prefix . $base36[$c]),
                "Tampered check char {$base36[$c]} should be rejected for prefix {$prefix}"
            );
        }
    }

    public function test_rejects_malformed_length(): void
    {
        $this->assertFalse(ValidGstin::hasValidChecksum('24ABCDE1234F1Z'));       // 14 chars
        $this->assertFalse(ValidGstin::hasValidChecksum('24ABCDE1234F1ZJZ'));     // 16 chars
    }

    public function test_rejects_invalid_characters(): void
    {
        $this->assertFalse(ValidGstin::hasValidChecksum('24ABCDE1234F1Z!'));
    }
}
