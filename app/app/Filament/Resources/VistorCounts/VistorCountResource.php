<?php

namespace App\Filament\Resources\VistorCounts;

use App\Filament\Resources\VistorCounts\Pages\ManageVistorCounts;
use App\Models\VistorCount;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VistorCountResource extends Resource
{
    protected static ?string $model = VistorCount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $recordTitleAttribute = 'ip_address';

    protected static UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $label = 'Visitor';

    protected static ?string $pluralLabel = 'Visitors';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Visit Information')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Visit ID')
                            ->copyable(),
                        TextEntry::make('visited_at')
                            ->label('Visited At')
                            ->dateTime('F j, Y g:i:s A')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('created_at')
                            ->label('Recorded At')
                            ->dateTime('F j, Y g:i:s A')
                            ->icon('heroicon-o-calendar'),
                    ])
                    ->columns(3),

                Section::make('Page Details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('post.title')
                            ->label('Post Title')
                            ->placeholder('Not a post page')
                            ->icon('heroicon-o-document')
                            ->url(fn ($record) => $record->post ? route('posts.show', $record->post->slug) : null, true),
                        TextEntry::make('route_name')
                            ->label('Route Name')
                            ->placeholder('No route')
                            ->icon('heroicon-o-map'),
                        TextEntry::make('url')
                            ->label('Full URL')
                            ->copyable()
                            ->icon('heroicon-o-link')
                            ->url(fn ($record) => $record->url, true),
                        TextEntry::make('path')
                            ->label('Path')
                            ->icon('heroicon-o-folder'),
                    ])
                    ->columns(2),

                Section::make('Visitor Information')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable()
                            ->icon('heroicon-o-server'),
                        TextEntry::make('session_id')
                            ->label('Session ID')
                            ->copyable()
                            ->icon('heroicon-o-key')
                            ->limit(30),
                        TextEntry::make('user_agent')
                            ->label('User Agent / Browser')
                            ->icon('heroicon-o-computer-desktop')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->icon('heroicon-o-information-circle')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('F j, Y g:i:s A'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('F j, Y g:i:s A')
                            ->placeholder('Not deleted')
                            ->visible(fn ($record) => $record->trashed()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip_address')
            ->columns([
                TextColumn::make('post.title')
                    ->searchable()
                    ->placeholder('N/A')
                    ->limit(30),
                TextColumn::make('path')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('ip_address')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-server'),
                TextColumn::make('visited_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('visited_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVistorCounts::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
