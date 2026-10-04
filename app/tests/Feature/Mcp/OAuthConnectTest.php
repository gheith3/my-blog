<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CONNECTOR_REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

beforeEach(function () {
    $this->artisan('passport:client', ['--personal' => true, '--name' => 'Test personal access client', '--provider' => 'users', '--no-interaction' => true]);
});

function pkcePair(): array
{
    $verifier = Str::random(64);

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

it('sends a guest to sign in before the consent screen', function () {
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [CONNECTOR_REDIRECT],
    ])->json('client_id');

    [, $challenge] = pkcePair();

    $this->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => CONNECTOR_REDIRECT,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => 'abc',
        'scope' => 'mcp:use',
    ]))->assertRedirect(route('filament.dashboard.auth.login'));
});

it('connects a client through the OAuth consent flow and calls the MCP server', function () {
    // 1. The connector registers itself (RFC 7591), as Claude does.
    $registration = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [CONNECTOR_REDIRECT],
    ])->assertCreated();

    $clientId = $registration->json('client_id');

    [$verifier, $challenge] = pkcePair();
    $state = Str::random(16);

    // 2. The user, signed in, approves on the consent screen.
    $user = User::factory()->create();
    $authorizeParams = [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => CONNECTOR_REDIRECT,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => $state,
        'scope' => 'mcp:use',
    ];

    $consent = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($authorizeParams))
        ->assertOk();

    $approval = $this->actingAs($user)->post('/oauth/authorize', [
        'state' => $state,
        'client_id' => $clientId,
        'auth_token' => $consent->viewData('authToken'),
    ]);

    $approval->assertRedirect();
    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    expect($callback['state'])->toBe($state)
        ->and($callback)->toHaveKey('code');

    // 3. The connector trades the code for a token, proving the PKCE verifier.
    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => CONNECTOR_REDIRECT,
        'code' => $callback['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    // 4. The token works against the MCP endpoint.
    $this->postJson('/mcp/blog', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-posts', 'arguments' => []]], [
        'Authorization' => 'Bearer '.$token->json('access_token'),
        'Accept' => 'application/json, text/event-stream',
    ])->assertOk()
        ->assertJsonPath('result.isError', false);
});
