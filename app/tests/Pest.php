<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Call a tool on the Blog MCP server over its JSON-RPC HTTP endpoint.
 */
function callBlogTool(string $name, array $arguments = []): array
{
    $response = test()->postJson('/mcp/blog', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $arguments],
    ], ['Accept' => 'application/json, text/event-stream']);

    return $response->json();
}

function blogToolText(array $response): string
{
    return $response['result']['content'][0]['text'];
}

/**
 * Decode the JSON payload of a v2 tool response (receipt, read result or the
 * single error shape from Blog MCP v2 section 2).
 */
function blogToolPayload(array $response): array
{
    return json_decode(blogToolText($response), true, 512, JSON_THROW_ON_ERROR);
}

function blogToolError(array $response): array
{
    expect($response['result']['isError'])->toBeTrue();

    return blogToolPayload($response)['error'];
}
