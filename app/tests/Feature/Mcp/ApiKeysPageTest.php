<?php

use App\Filament\Pages\ApiKeys;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // passport:install creates this in dev, but RefreshDatabase starts empty.
    $this->artisan('passport:client', ['--personal' => true, '--name' => 'Test personal access client', '--provider' => 'users', '--no-interaction' => true]);
});

it('lets a read-only key list posts over MCP but not create them', function () {
    $user = User::factory()->create();
    // Same scope set the API Keys page grants for "Read only".
    $plaintext = $user->createToken('Test agent', ['posts:read', 'comments:read'])->accessToken;

    $headers = ['Authorization' => "Bearer {$plaintext}", 'Accept' => 'application/json, text/event-stream'];

    $listed = $this->postJson('/mcp/blog', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-posts', 'arguments' => []]], $headers);
    $created = $this->postJson('/mcp/blog', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'create-post', 'arguments' => [
        'title' => 'Blocked',
        'content' => 'Nope.',
        'category_id' => 1,
    ]]], $headers);

    expect($listed->json('result.isError'))->toBeFalsy()
        ->and($created->json('result.isError'))->toBeTrue();
});

it('renders the API Keys page with the MCP URL', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard/api-keys')
        ->assertOk()
        ->assertSee('/mcp/blog');
});

it('creates a key from the page, shows it once, and clears it on dismiss', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(ApiKeys::class)
        ->callAction('create', ['name' => 'Test agent', 'ability' => 'read'])
        ->assertHasNoActionErrors();

    expect($page->instance()->newTokenPlaintext)->not->toBeEmpty()
        ->and($user->tokens()->where('name', 'Test agent')->exists())->toBeTrue();

    $page->call('dismissNewToken')
        ->assertSet('newTokenPlaintext', null);
});
