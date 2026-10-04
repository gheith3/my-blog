<?php

use App\Services\PostService;

it('returns short paragraphs unchanged without an ellipsis', function () {
    $excerpt = (new PostService)->generateExcerpt('<p>A short paragraph.</p><p>More.</p>');

    expect($excerpt)->toBe('A short paragraph.');
});

it('cuts long paragraphs at a word boundary and ends with an ellipsis', function () {
    $words = implode(' ', array_fill(0, 60, 'كلمات'));
    $excerpt = (new PostService)->generateExcerpt("<p>{$words}</p>");

    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(300)
        ->and($excerpt)->toEndWith('…')
        ->and($words)->toStartWith(mb_substr($excerpt, 0, -1).' ');
});

it('decodes entities before measuring', function () {
    $excerpt = (new PostService)->generateExcerpt('<p>He said &quot;hello&quot; &amp; left.</p>');

    expect($excerpt)->toBe('He said "hello" & left.');
});

it('handles plain text without HTML tags', function () {
    $excerpt = (new PostService)->generateExcerpt('Plain first line');

    expect($excerpt)->toBe('Plain first line');
});

it('returns an empty string for empty content', function () {
    expect((new PostService)->generateExcerpt(''))->toBe('');
});
