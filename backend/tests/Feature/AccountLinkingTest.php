<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('account-linking')]
    public function it_rejects_linking_to_self_with_same_account_code(): void
    {
        $this->markTestSkipped('Account linking by code is disabled.');
    }

    #[Test]
    #[Group('account-linking')]
    public function it_links_provider_ids_from_current_user_to_owner_user(): void
    {
        $this->markTestSkipped('Account linking by code is disabled.');
    }
}
