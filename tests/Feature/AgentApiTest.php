<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Learning;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // AGENT REGISTRATION
    // ─────────────────────────────────────────────────────────────

    public function test_agent_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/agent/register', [
            'name' => 'TestAgent',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'agent_id',
                'api_key',
                'status',
                'message',
            ])
            ->assertJson([
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('agents', [
            'name' => 'TestAgent',
            'email' => 'test@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_agent_registration_requires_name(): void
    {
        $response = $this->postJson('/api/agent/register', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_agent_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/agent/register', [
            'name' => 'TestAgent',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_agent_registration_requires_unique_email(): void
    {
        $this->postJson('/api/agent/register', [
            'name' => 'First',
            'email' => 'duplicate@example.com',
        ]);

        $response = $this->postJson('/api/agent/register', [
            'name' => 'Second',
            'email' => 'duplicate@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registered_agent_receives_api_key(): void
    {
        $response = $this->postJson('/api/agent/register', [
            'name' => 'KeyTest',
            'email' => 'keytest@example.com',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('api_key'));
        $this->assertEquals(64, strlen($response->json('api_key')));
    }

    public function test_registered_agent_receives_verification_token(): void
    {
        $response = $this->postJson('/api/agent/register', [
            'name' => 'TokenTest',
            'email' => 'tokentest@example.com',
        ]);

        $response->assertStatus(201);
        $agent = Agent::where('email', 'tokentest@example.com')->first();
        $this->assertNotNull($agent->verification_token);
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL VERIFICATION
    // ─────────────────────────────────────────────────────────────

    public function test_agent_email_can_be_verified_with_valid_token(): void
    {
        $agent = Agent::create([
            'name' => 'VerifyTest',
            'email' => 'verify@example.com',
            'api_key' => Str::random(64),
            'verification_token' => 'valid-token-123',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/agent/verify/valid-token-123');

        $response->assertStatus(302); // redirect

        $agent->refresh();
        $this->assertEquals('active', $agent->status);
        $this->assertNull($agent->verification_token);
        $this->assertNotNull($agent->email_verified_at);
    }

    public function test_verification_with_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/agent/verify/invalid-token');

        $response->assertStatus(404);
    }

    public function test_verified_agent_cannot_be_verified_again(): void
    {
        $agent = Agent::create([
            'name' => 'AlreadyVerified',
            'email' => 'already@example.com',
            'api_key' => Str::random(64),
            'verification_token' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->getJson("/api/agent/verify/some-token");

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────
    // RESEND VERIFICATION
    // ─────────────────────────────────────────────────────────────

    public function test_can_resend_verification_email(): void
    {
        $agent = Agent::create([
            'name' => 'ResendTest',
            'email' => 'resend@example.com',
            'api_key' => Str::random(64),
            'verification_token' => 'old-token',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/agent/resend-verification', [
            'agent_id' => $agent->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Verification email sent.']);

        $agent->refresh();
        $this->assertNotEquals('old-token', $agent->verification_token);
    }

    public function test_resend_fails_for_already_active_agent(): void
    {
        $agent = Agent::create([
            'name' => 'ActiveAgent',
            'email' => 'active@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/agent/resend-verification', [
            'agent_id' => $agent->id,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Agent already verified.']);
    }

    public function test_resend_requires_valid_agent_id(): void
    {
        $response = $this->postJson('/api/agent/resend-verification', [
            'agent_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agent_id']);
    }

    // ─────────────────────────────────────────────────────────────
    // PROTECTED ROUTES — REQUIRES API KEY
    // ─────────────────────────────────────────────────────────────

    public function test_protected_route_requires_api_key(): void
    {
        $response = $this->postJson('/api/learnings', [
            'title' => 'Test',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'api_key_required',
            ]);
    }

    public function test_protected_route_rejects_invalid_api_key(): void
    {
        $response = $this->postJson('/api/learnings', [
            'title' => 'Test',
            'body' => 'Body',
            'category' => 'nginx',
        ], ['X-API-Key' => 'invalid-key']);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_api_key',
            ]);
    }

    public function test_protected_route_rejects_unverified_agent(): void
    {
        $agent = Agent::create([
            'name' => 'Unverified',
            'email' => 'unverified@example.com',
            'api_key' => 'test-key-123',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/learnings', [
            'title' => 'Test',
            'body' => 'Body',
            'category' => 'nginx',
        ], ['X-API-Key' => 'test-key-123']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'email_not_verified',
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // AGENT PROFILE (PROTECTED)
    // ─────────────────────────────────────────────────────────────

    public function test_verified_agent_can_view_own_profile(): void
    {
        $agent = Agent::create([
            'name' => 'MeTest',
            'email' => 'me@example.com',
            'api_key' => 'me-key-123',
            'status' => 'active',
            'email_verified_at' => now(),
            'trust_score' => 5,
        ]);

        $response = $this->getJson('/api/agent/me', [
            'X-API-Key' => 'me-key-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'MeTest',
                'email' => 'me@example.com',
                'status' => 'active',
                'trust_score' => 5,
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PUBLIC AGENT PROFILE
    // ─────────────────────────────────────────────────────────────

    public function test_anyone_can_view_public_agent_profile(): void
    {
        $agent = Agent::create([
            'name' => 'PublicAgent',
            'email' => 'public@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
            'trust_score' => 10,
        ]);

        $response = $this->getJson("/api/agent/{$agent->id}");

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'PublicAgent',
                'trust_score' => 10,
            ]);
    }

    public function test_public_agent_profile_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/agent/99999');

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────
    // LEARNINGS — PUBLIC READ
    // ─────────────────────────────────────────────────────────────

    public function test_anyone_can_list_learnings(): void
    {
        $agent = Agent::create([
            'name' => 'LAgent',
            'email' => 'lagent@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Test Learning',
            'body' => 'Test body content',
            'category' => 'nginx',
            'tags' => ['test', 'nginx'],
        ]);

        $response = $this->getJson('/api/learnings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'body', 'category', 'tags', 'verified_count', 'failed_count'],
                ],
                'current_page',
                'last_page',
            ]);
    }

    public function test_learnings_include_agent_info(): void
    {
        $agent = Agent::create([
            'name' => 'IncludeTest',
            'email' => 'include@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Include Test',
            'body' => 'Body',
            'category' => 'linux',
        ]);

        $response = $this->getJson('/api/learnings');

        $response->assertStatus(200);
        $this->assertEquals('IncludeTest', $response->json('data.0.agent.name'));
    }

    public function test_learnings_can_be_filtered_by_category(): void
    {
        $agent = Agent::create([
            'name' => 'CatAgent',
            'email' => 'cat@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Nginx Learning',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Linux Learning',
            'body' => 'Body',
            'category' => 'linux',
        ]);

        $response = $this->getJson('/api/learnings?category=nginx');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('Nginx Learning', $response->json('data.0.title'));
    }

    public function test_can_view_single_learning(): void
    {
        $agent = Agent::create([
            'name' => 'SingleAgent',
            'email' => 'single@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Single Learning',
            'body' => 'Single body',
            'category' => 'database',
            'tags' => ['single'],
        ]);

        $response = $this->getJson("/api/learnings/{$learning->id}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Single Learning',
                'body' => 'Single body',
            ]);
    }

    public function test_single_learning_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/learnings/99999');

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────
    // LEARNINGS — PROTECTED WRITE
    // ─────────────────────────────────────────────────────────────

    public function test_verified_agent_can_publish_learning(): void
    {
        $agent = Agent::create([
            'name' => 'PublishAgent',
            'email' => 'publish@example.com',
            'api_key' => 'publish-key-123',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/learnings', [
            'title' => 'New Learning',
            'body' => 'Learning body content',
            'category' => 'nginx',
            'tags' => ['nginx', 'performance'],
        ], ['X-API-Key' => 'publish-key-123']);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'message'])
            ->assertJson(['message' => 'Learning published successfully.']);

        $this->assertDatabaseHas('learnings', [
            'title' => 'New Learning',
            'agent_id' => $agent->id,
        ]);
    }

    public function test_publish_learning_requires_title(): void
    {
        $agent = Agent::create([
            'name' => 'NoTitle',
            'email' => 'notitle@example.com',
            'api_key' => 'notitle-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/learnings', [
            'body' => 'Body',
            'category' => 'nginx',
        ], ['X-API-Key' => 'notitle-key']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_publish_learning_requires_valid_category(): void
    {
        $agent = Agent::create([
            'name' => 'BadCat',
            'email' => 'badcat@example.com',
            'api_key' => 'badcat-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/learnings', [
            'title' => 'Test',
            'body' => 'Body',
            'category' => 'invalid-category',
        ], ['X-API-Key' => 'badcat-key']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    // ─────────────────────────────────────────────────────────────
    // VERIFICATIONS
    // ─────────────────────────────────────────────────────────────

    public function test_verified_agent_can_verify_a_learning(): void
    {
        $agent1 = Agent::create([
            'name' => 'LearningAuthor',
            'email' => 'author@example.com',
            'api_key' => 'author-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $agent2 = Agent::create([
            'name' => 'Verifier',
            'email' => 'verifier@example.com',
            'api_key' => 'verifier-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent1->id,
            'title' => 'To Verify',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $response = $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'success',
            'context' => 'Ubuntu 22.04, 4GB RAM',
        ], ['X-API-Key' => 'verifier-key']);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('verifications', [
            'learning_id' => $learning->id,
            'agent_id' => $agent2->id,
            'status' => 'success',
        ]);

        $learning->refresh();
        $this->assertEquals(1, $learning->verified_count);
        $agent2->refresh();
        $this->assertEquals(1, $agent2->trust_score);
    }

    public function test_agent_can_mark_verification_as_failed(): void
    {
        $agent1 = Agent::create([
            'name' => 'FAuth',
            'email' => 'fauth@example.com',
            'api_key' => 'fauth-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $agent2 = Agent::create([
            'name' => 'FFail',
            'email' => 'ffail@example.com',
            'api_key' => 'ffail-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent1->id,
            'title' => 'To Fail',
            'body' => 'Body',
            'category' => 'docker',
        ]);

        $response = $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'failed',
            'context' => 'Did not work on CentOS 8',
        ], ['X-API-Key' => 'ffail-key']);

        $response->assertStatus(201)
            ->assertJson(['status' => 'failed']);

        $learning->refresh();
        $this->assertEquals(1, $learning->failed_count);
    }

    public function test_agent_cannot_verify_own_learning(): void
    {
        $agent = Agent::create([
            'name' => 'SelfVerify',
            'email' => 'self@example.com',
            'api_key' => 'self-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Self',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $response = $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'success',
            'context' => 'Worked for me',
        ], ['X-API-Key' => 'self-key']);

        $response->assertStatus(403);
    }

    public function test_agent_cannot_verify_same_learning_twice(): void
    {
        $agent1 = Agent::create([
            'name' => 'L1',
            'email' => 'l1@example.com',
            'api_key' => 'l1-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $agent2 = Agent::create([
            'name' => 'L2',
            'email' => 'l2@example.com',
            'api_key' => 'l2-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent1->id,
            'title' => 'Once',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'success',
        ], ['X-API-Key' => 'l2-key']);

        $response = $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'success',
        ], ['X-API-Key' => 'l2-key']);

        $response->assertStatus(409)
            ->assertJson(['error' => 'already_verified']);
    }

    public function test_verification_requires_valid_status(): void
    {
        $agent1 = Agent::create([
            'name' => 'V1',
            'email' => 'v1@example.com',
            'api_key' => 'v1-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $agent2 = Agent::create([
            'name' => 'V2',
            'email' => 'v2@example.com',
            'api_key' => 'v2-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent1->id,
            'title' => 'Invalid Status',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $response = $this->postJson("/api/verify/{$learning->id}", [
            'status' => 'maybe',
        ], ['X-API-Key' => 'v2-key']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ─────────────────────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────────────────────

    public function test_can_search_learnings(): void
    {
        $agent = Agent::create([
            'name' => 'SearchAgent',
            'email' => 'search@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Nginx Performance Tuning Guide',
            'body' => 'How to tune nginx configuration for high traffic production environments',
            'category' => 'nginx',
            'tags' => ['nginx', 'performance'],
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'MySQL Configuration',
            'body' => 'Optimize MySQL settings',
            'category' => 'database',
        ]);

        // Use category filter since FULLTEXT requires seeded data to be present
        $response = $this->getJson('/api/search?category=nginx');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_search_can_filter_by_category(): void
    {
        $agent = Agent::create([
            'name' => 'CatSearch',
            'email' => 'catsearch@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Docker Networking',
            'body' => 'Docker networking guide',
            'category' => 'docker',
        ]);

        Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Docker Storage',
            'body' => 'Docker storage guide',
            'category' => 'docker',
        ]);

        $response = $this->getJson('/api/search?category=docker');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    // ─────────────────────────────────────────────────────────────
    // REVOKE API KEY
    // ─────────────────────────────────────────────────────────────

    public function test_verified_agent_can_revoke_own_api_key(): void
    {
        $agent = Agent::create([
            'name' => 'RevokeTest',
            'email' => 'revoke@example.com',
            'api_key' => 'revoke-key-123',
            'status' => 'active',
            'email_verified_at' => now(),
            'trust_score' => 5,
        ]);

        $response = $this->postJson('/api/agent/revoke', [], [
            'X-API-Key' => 'revoke-key-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'API key revoked successfully. Your account and all contributions are preserved. To regain API access, you must register a new account.',
            ]);

        $agent->refresh();
        $this->assertEquals('REVOKED', $agent->api_key);
        $this->assertEquals('active', $agent->status);
        $this->assertEquals(5, $agent->trust_score);
    }

    public function test_revoked_api_key_cannot_access_protected_routes(): void
    {
        $agent = Agent::create([
            'name' => 'RevokeProtected',
            'email' => 'revoke-protect@example.com',
            'api_key' => 'revoke-protect-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/agent/revoke', [], ['X-API-Key' => 'revoke-protect-key']);

        $response = $this->getJson('/api/agent/me', [
            'X-API-Key' => 'revoke-protect-key',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_api_key',
                'message' => 'Invalid API key.',
            ]);
    }

    public function test_revoke_requires_authentication(): void
    {
        $response = $this->postJson('/api/agent/revoke');

        $response->assertStatus(401)
            ->assertJson(['error' => 'api_key_required']);
    }

    public function test_revoke_requires_verified_email(): void
    {
        $agent = Agent::create([
            'name' => 'RevokeUnverified',
            'email' => 'revoke-unverified@example.com',
            'api_key' => 'revoke-unverified-key',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/agent/revoke', [], [
            'X-API-Key' => 'revoke-unverified-key',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'email_not_verified']);
    }

    public function test_suspended_agent_cannot_revoke(): void
    {
        $agent = Agent::create([
            'name' => 'RevokeSuspended',
            'email' => 'revoke-suspended@example.com',
            'api_key' => 'revoke-suspended-key',
            'status' => 'suspended',
            'trust_score' => 0,
        ]);

        $response = $this->postJson('/api/agent/revoke', [], [
            'X-API-Key' => 'revoke-suspended-key',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'agent_suspended']);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE ACCOUNT
    // ─────────────────────────────────────────────────────────────

    public function test_verified_agent_can_delete_own_account(): void
    {
        $agent = Agent::create([
            'name' => 'DeleteTest',
            'email' => 'delete@example.com',
            'api_key' => 'delete-key-123',
            'status' => 'active',
            'email_verified_at' => now(),
            'trust_score' => 10,
        ]);

        $response = $this->deleteJson('/api/agent/account', [], [
            'X-API-Key' => 'delete-key-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account and all contributions have been permanently deleted.',
            ]);

        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_delete_removes_agent_learnings_and_verifications(): void
    {
        $agent = Agent::create([
            'name' => 'DeleteContributions',
            'email' => 'delete-contrib@example.com',
            'api_key' => 'delete-contrib-key',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $learning = Learning::create([
            'agent_id' => $agent->id,
            'title' => 'Will Be Deleted',
            'body' => 'Body',
            'category' => 'nginx',
        ]);

        $otherAgent = Agent::create([
            'name' => 'OtherAgent',
            'email' => 'other@example.com',
            'api_key' => Str::random(64),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Verification::create([
            'learning_id' => $learning->id,
            'agent_id' => $otherAgent->id,
            'status' => 'success',
            'context' => 'Test',
        ]);

        $this->deleteJson('/api/agent/account', [], ['X-API-Key' => 'delete-contrib-key']);

        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
        $this->assertDatabaseMissing('learnings', ['id' => $learning->id]);
        $this->assertDatabaseMissing('verifications', ['agent_id' => $agent->id]);
        $this->assertDatabaseHas('agents', ['id' => $otherAgent->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/agent/account');

        $response->assertStatus(401)
            ->assertJson(['error' => 'api_key_required']);
    }

    public function test_delete_requires_verified_email(): void
    {
        $agent = Agent::create([
            'name' => 'DeleteUnverified',
            'email' => 'delete-unverified@example.com',
            'api_key' => 'delete-unverified-key',
            'status' => 'pending',
        ]);

        $response = $this->deleteJson('/api/agent/account', [], [
            'X-API-Key' => 'delete-unverified-key',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'email_not_verified']);
    }

    public function test_suspended_agent_cannot_delete(): void
    {
        $agent = Agent::create([
            'name' => 'DeleteSuspended',
            'email' => 'delete-suspended@example.com',
            'api_key' => 'delete-suspended-key',
            'status' => 'suspended',
            'trust_score' => 0,
        ]);

        $response = $this->deleteJson('/api/agent/account', [], [
            'X-API-Key' => 'delete-suspended-key',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'agent_suspended']);
    }
}
