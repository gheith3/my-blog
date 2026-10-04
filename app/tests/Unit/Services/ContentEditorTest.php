<?php

use App\Exceptions\EditFailedException;
use App\Services\ContentEditor;

beforeEach(function () {
    $this->editor = new ContentEditor;
});

/**
 * Run an apply call that is expected to fail and return the exception.
 */
function failingApply(ContentEditor $editor, string $html, array $edits, bool $wholeWordDefault = false): EditFailedException
{
    try {
        $editor->apply($html, $edits, $wholeWordDefault);
    } catch (EditFailedException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected EditFailedException was not thrown.');
}

test('old containing a double quote matches text stored as &quot;', function () {
    $html = '<p style="text-align: justify;">قال لها: &quot;انت نيتك تفضحينا؟&quot; فصمتت.</p>';

    $result = $this->editor->apply($html, [
        ['old' => '"انت نيتك تفضحينا؟"', 'new' => '"انت نيتك تفضحينا!"'],
    ]);

    expect($result)->toBe('<p style="text-align: justify;">قال لها: &quot;انت نيتك تفضحينا!&quot; فصمتت.</p>');
});

test('ambiguous old fails with edit_ambiguous reporting count and paragraphs', function () {
    $html = '<p>قال أيضا شيئا.</p><p style="text-align: justify;">ثم قال أيضا أخرى.</p>';

    $exception = failingApply($this->editor, $html, [['old' => 'أيضا', 'new' => 'أيضاً']]);

    expect($exception->failures)->toHaveCount(1)
        ->and($exception->failures[0]['index'])->toBe(0)
        ->and($exception->failures[0]['code'])->toBe('edit_ambiguous')
        ->and($exception->failures[0]['count'])->toBe(2)
        ->and($exception->failures[0]['paragraphs'])->toBe([1, 2]);
});

test('occurrence picks one match out of several', function () {
    $html = '<p>قال أيضا شيئا.</p><p style="text-align: justify;">ثم قال أيضا أخرى.</p>';

    $result = $this->editor->apply($html, [
        ['old' => 'أيضا', 'new' => 'أيضاً', 'occurrence' => 2],
    ]);

    expect($result)->toBe('<p>قال أيضا شيئا.</p><p style="text-align: justify;">ثم قال أيضاً أخرى.</p>');
});

test('whole_word does not match مباغتا inside مباغتاً', function () {
    $html = '<p>كان القرار مباغتاً للجميع.</p>';

    $exception = failingApply($this->editor, $html, [
        ['old' => 'مباغتا', 'new' => 'مفاجئا', 'whole_word' => true],
    ]);

    expect($exception->failures[0]['code'])->toBe('edit_not_found');
});

test('whole_word matches a standalone word and the call-level default applies', function () {
    $html = '<p>حدث الأمر مباغتا في الليل.</p>';

    $result = $this->editor->apply($html, [
        ['old' => 'مباغتا', 'new' => 'مباغتاً'],
    ], wholeWordDefault: true);

    expect($result)->toBe('<p>حدث الأمر مباغتاً في الليل.</p>');
});

test('old differing only by U+200F fails with a near match naming that character', function () {
    $html = '<p>ولا تستأذن'."\u{200F}".' أحد، بل امضِ.</p>';

    $exception = failingApply($this->editor, $html, [
        ['old' => 'ولا تستأذن أحد،', 'new' => 'ولا تستأذن أحداً،'],
    ]);

    expect($exception->failures[0]['code'])->toBe('edit_not_found')
        ->and($exception->failures[0]['near_matches'])->toHaveCount(1)
        ->and($exception->failures[0]['near_matches'][0]['paragraph'])->toBe(1)
        ->and($exception->failures[0]['near_matches'][0]['text'])->toBe('ولا تستأذن'."\u{200F}".' أحد،')
        ->and($exception->failures[0]['near_matches'][0]['difference'])->toBe('invisible character U+200F');
});

test('old differing only by harakat gets a near match naming the haraka', function () {
    $html = '<p>سكتَ طويلا.</p>';

    $exception = failingApply($this->editor, $html, [['old' => 'سكت طويلا', 'new' => 'صمت طويلا']]);

    expect($exception->failures[0]['code'])->toBe('edit_not_found')
        ->and($exception->failures[0]['near_matches'][0]['difference'])->toBe('haraka U+064E');
});

test('new containing markup is stored escaped, never as markup', function () {
    $result = $this->editor->apply('<p>نص هنا.</p>', [
        ['old' => 'نص', 'new' => '<b>نص</b>'],
    ]);

    expect($result)->toBe('<p>&lt;b&gt;نص&lt;/b&gt; هنا.</p>')
        ->and($result)->not->toContain('<b>');
});

test('style attributes survive edits and untouched blocks stay byte-identical', function () {
    $untouched = '<p style="text-align: justify;">قال: &quot;مرحبا&quot; وانتهى&nbsp;هنا.</p>';
    $html = '<p style="text-align: justify;">افتتاحية النص.</p>'.$untouched;

    $result = $this->editor->apply($html, [['old' => 'افتتاحية النص.', 'new' => 'تمهيد.']]);

    expect($result)->toBe('<p style="text-align: justify;">تمهيد.</p>'.$untouched);
});

test('overlapping edits fail with edit_overlap reporting the indexes', function () {
    $html = '<p>نص واحد هنا.</p>';

    $exception = failingApply($this->editor, $html, [
        ['old' => 'نص واحد', 'new' => 'أول'],
        ['old' => 'واحد هنا', 'new' => 'ثان'],
    ]);

    expect($exception->failures)->toHaveCount(2)
        ->and($exception->failures[0]['code'])->toBe('edit_overlap')
        ->and($exception->failures[0]['indexes'])->toBe([0, 1])
        ->and($exception->failures[1]['code'])->toBe('edit_overlap')
        ->and($exception->failures[1]['indexes'])->toBe([0, 1])
        ->and($exception->getMessage())->toBe('2 of 2 edits could not be applied. Nothing was saved.');
});

test('a match crossing inline formatting fails with edit_spans_markup', function () {
    $html = '<p style="text-align: justify;">سحبت يداه <b>من التوغل</b> رويدا.</p>';

    $exception = failingApply($this->editor, $html, [
        ['old' => 'سحبت يداه من التوغل', 'new' => 'سحبتُ يديه من التوغل'],
    ]);

    expect($exception->failures[0]['code'])->toBe('edit_spans_markup')
        ->and($exception->failures[0]['paragraph'])->toBe(1);
});

test('empty new deletes old', function () {
    $result = $this->editor->apply('<p>أول وسط آخر.</p>', [['old' => ' وسط', 'new' => '']]);

    expect($result)->toBe('<p>أول آخر.</p>');
});

test('paragraph restricts the search to one block', function () {
    $html = '<p>أيضا هنا.</p><p style="text-align: justify;">قال أيضا هناك.</p>';

    $result = $this->editor->apply($html, [
        ['old' => 'أيضا', 'new' => 'أيضاً', 'paragraph' => 2],
    ]);

    expect($result)->toBe('<p>أيضا هنا.</p><p style="text-align: justify;">قال أيضاً هناك.</p>');
});

test('edits never see each others output', function () {
    $result = $this->editor->apply('<p>أ ب</p>', [
        ['old' => 'أ', 'new' => 'ب'],
        ['old' => 'ب', 'new' => 'ج'],
    ]);

    expect($result)->toBe('<p>ب ج</p>');
});

test('a single bad edit fails the whole call with the spec error shape', function () {
    $html = '<p>سحبت يداه من التوغل.</p>';

    $exception = failingApply($this->editor, $html, [
        ['old' => 'سحبت يداه', 'new' => 'سحبتُ يديه'],
        ['old' => 'غير موجود', 'new' => 'شيء'],
    ]);

    expect($exception->failures)->toHaveCount(1)
        ->and($exception->failures[0]['index'])->toBe(1)
        ->and($exception->getMessage())->toBe('1 of 2 edits could not be applied. Nothing was saved.');

    $error = $exception->toError();

    expect($error['error']['code'])->toBe('edit_failed')
        ->and($error['error']['message'])->toBe('1 of 2 edits could not be applied. Nothing was saved.')
        ->and($error['error']['details']['failures'])->toBe($exception->failures);
});

test('validate returns a diff with about 40 characters of context and no html', function () {
    $lead = str_repeat('كلمة ', 20);
    $trail = str_repeat('خاتمة ', 20);
    $html = '<p style="text-align: justify;">'.$lead.'سحبت يداه من التوغل'.$trail.'</p>';

    $diff = $this->editor->validate($html, [
        ['old' => 'سحبت يداه من التوغل', 'new' => 'سحبتُ يديه من التوغل'],
    ]);

    expect($diff)->toHaveCount(1)
        ->and($diff[0]['index'])->toBe(0)
        ->and($diff[0]['paragraph'])->toBe(1)
        ->and($diff[0]['before'])->toStartWith('...')
        ->and($diff[0]['before'])->toEndWith('...')
        ->and($diff[0]['before'])->toContain('سحبت يداه من التوغل')
        ->and($diff[0]['after'])->toContain('سحبتُ يديه من التوغل')
        ->and(mb_strlen($diff[0]['before']))->toBeLessThanOrEqual(mb_strlen('سحبت يداه من التوغل') + 80 + 6);
});

test('validate throws the same failure as apply without producing html', function () {
    $html = '<p>نص.</p>';

    try {
        $this->editor->validate($html, [['old' => 'غائب', 'new' => 'حاضر']]);
        $this->fail('Expected EditFailedException was not thrown.');
    } catch (EditFailedException $exception) {
        expect($exception->failures[0]['code'])->toBe('edit_not_found');
    }
});

test('apply with no edits returns the html byte-identical', function () {
    $html = '<p style="text-align: justify;">قال: &quot;مرحبا&quot;.</p>';

    expect($this->editor->apply($html, []))->toBe($html);
});

test('empty old is rejected as structurally invalid', function () {
    $this->editor->apply('<p>نص.</p>', [['old' => '', 'new' => 'شيء']]);
})->throws(InvalidArgumentException::class);
