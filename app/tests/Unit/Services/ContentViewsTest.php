<?php

use App\Services\ContentViews;

beforeEach(function () {
    $this->views = new ContentViews;
});

test('toText decodes entities and keeps harakat and no-break spaces', function () {
    $html = '<p style="text-align: justify;">قال لها: &quot;انت نيتك تفضحينا؟&quot; ثم سكتَ.</p>'
        .'<p style="text-align: justify;">خطٌّ واحد&nbsp;هنا.</p>';

    $text = $this->views->toText($html);

    expect($text)->toBe(
        'قال لها: "انت نيتك تفضحينا؟" ثم سكتَ.'
        ."\n\n"
        .'خطٌّ واحد'."\u{00A0}".'هنا.'
    );
});

test('toText separates every top-level block with a blank line', function () {
    $html = '<h2>عنوان</h2><p>أول.</p><ul><li>عنصر أول</li><li>عنصر ثانٍ</li></ul><blockquote>اقتباس.</blockquote>';

    expect($this->views->toText($html))->toBe(
        "عنوان\n\nأول.\n\nعنصر أول\n\nعنصر ثانٍ\n\nاقتباس."
    );
});

test('toParagraphs returns 1-based indexes, types, decoded text and serialized html', function () {
    $html = '<p style="text-align: justify;">أول &quot;مقطع&quot;.</p>'
        .'<h2>عنوان فرعي</h2>'
        .'<ul><li>عنصر</li></ul>'
        .'<blockquote>اقتباس.</blockquote>';

    $paragraphs = $this->views->toParagraphs($html);

    expect($paragraphs)->toBe([
        [
            'index' => 1,
            'type' => 'p',
            'text' => 'أول "مقطع".',
            'html' => '<p style="text-align: justify;">أول &quot;مقطع&quot;.</p>',
        ],
        ['index' => 2, 'type' => 'h2', 'text' => 'عنوان فرعي', 'html' => '<h2>عنوان فرعي</h2>'],
        ['index' => 3, 'type' => 'li', 'text' => 'عنصر', 'html' => '<li>عنصر</li>'],
        ['index' => 4, 'type' => 'blockquote', 'text' => 'اقتباس.', 'html' => '<blockquote>اقتباس.</blockquote>'],
    ]);
});

test('toMarkdown renders headings, bold, italic, links, lists and quotes', function () {
    $html = '<h2>عنوان فرعي</h2>'
        .'<p style="text-align: justify;">نص <strong>غامق</strong> و<em>مائل</em> و<a href="https://gheith.me">رابط</a>.</p>'
        .'<ul><li>أول</li><li>ثانٍ</li></ul>'
        .'<ol><li>مرقّم</li></ol>'
        .'<blockquote>اقتباس طويل.</blockquote>';

    expect($this->views->toMarkdown($html))->toBe(
        "## عنوان فرعي\n\n"
        .'نص **غامق** و*مائل* و[رابط](https://gheith.me).'."\n\n"
        ."- أول\n\n"
        ."- ثانٍ\n\n"
        ."1. مرقّم\n\n"
        .'> اقتباس طويل.'
    );
});

test('wordCount counts whitespace-separated words', function () {
    expect($this->views->wordCount('<p>واحدة اثنتان ثلاث.</p>'))->toBe(3)
        ->and($this->views->wordCount('<p></p>'))->toBe(0);
});

test('readingTimeMinutes uses 200 words per minute with a minimum of 1', function () {
    expect($this->views->readingTimeMinutes('<p>كلمة قليلة.</p>'))->toBe(1)
        ->and($this->views->readingTimeMinutes('<p>'.str_repeat('كلمة ', 250).'</p>'))->toBe(2);
});
