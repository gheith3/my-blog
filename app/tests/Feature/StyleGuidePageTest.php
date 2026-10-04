<?php

use App\Filament\Pages\StyleGuidePage;
use App\Models\User;
use App\Settings\StyleGuideSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The dashboard language switcher defaults to ar; assert English strings.
    app()->setLocale('en');

    $this->actingAs(User::factory()->create());
});

it('renders the writing style page in the settings navigation', function () {
    $this->get(StyleGuidePage::getUrl())
        ->assertOk()
        ->assertSee('Writing Style');
});

it('saves the style guide content and bumps its version', function () {
    $initialVersion = app(StyleGuideSettings::class)->version;

    Livewire::test(StyleGuidePage::class)
        ->set('data.content', "# House style\n- A new rule.")
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(StyleGuideSettings::class);

    expect($settings->content)->toBe("# House style\n- A new rule.")
        ->and($settings->version)->toBe($initialVersion + 1)
        ->and($settings->updated_at)->not->toBeNull();

    $storedVersion = DB::table('settings')
        ->where('group', 'style_guide')
        ->where('name', 'version')
        ->value('payload');

    expect((int) json_decode($storedVersion))->toBe($initialVersion + 1);
});

it('stamps updated_at with the save time', function () {
    $this->travelTo(now()->startOfSecond());

    Livewire::test(StyleGuidePage::class)
        ->set('data.content', '# Updated rules')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(StyleGuideSettings::class)->updated_at)
        ->toBe(now()->toIso8601String());
});
