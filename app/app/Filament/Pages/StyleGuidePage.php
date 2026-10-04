<?php

namespace App\Filament\Pages;

use App\Settings\StyleGuideSettings;
use BackedEnum;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The "Writing style" settings page (Blog MCP v2, section 8): one Markdown
 * document of house rules, versioned on every save. Agents read it through
 * the MCP style-guide resource; only the author edits it here.
 */
class StyleGuidePage extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string $settings = StyleGuideSettings::class;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.style_guide.navigation');
    }

    public function getTitle(): string
    {
        return __('filament.pages.style_guide.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.pages.style_guide.section'))
                    ->description(__('filament.pages.style_guide.section_description'))
                    ->schema([
                        MarkdownEditor::make('content')
                            ->label(__('filament.pages.style_guide.fields.content'))
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('version')
                            ->label(__('filament.pages.style_guide.fields.version'))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('updated_at')
                            ->label(__('filament.pages.style_guide.fields.updated_at'))
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Every save bumps the style guide version and stamps the time, so agents
     * can tell whether they have read the current rules.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['version'] = app(StyleGuideSettings::class)->version + 1;
        $data['updated_at'] = now()->toIso8601String();

        return $data;
    }
}
