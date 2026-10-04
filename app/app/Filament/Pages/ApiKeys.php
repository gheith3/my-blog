<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets the signed-in user mint and revoke Passport personal access tokens for
 * the blog MCP server (routes/ai.php). The manual alternative to the OAuth
 * "Connect" flow, for clients that cannot run it. Each token carries
 * posts:read/comments:read (read-only) or all four scopes (full access).
 */
class ApiKeys extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $title = 'API Keys';

    protected string $view = 'filament.pages.api-keys';

    public ?string $newTokenPlaintext = null;

    public ?string $newTokenName = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => auth()->user()->tokens()->getQuery()->where('revoked', false))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn ($record): string => $record->name ?: ($record->client?->name ?? 'Unnamed'))
                    ->searchable(),
                TextColumn::make('scopes')
                    ->label('Access')
                    ->badge()
                    ->state(fn ($record): string => in_array('posts:write', $record->scopes ?? [], true) || in_array('mcp:use', $record->scopes ?? [], true)
                        ? 'Full access'
                        : 'Read only')
                    ->color(fn ($record): string => in_array('posts:write', $record->scopes ?? [], true) || in_array('mcp:use', $record->scopes ?? [], true) ? 'warning' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Any agent using this key will lose access immediately.')
                    ->action(fn ($record) => $record->revoke()),
            ])
            ->emptyStateHeading('No API keys yet')
            ->emptyStateDescription('Create a key to let an AI agent connect to the blog.')
            ->emptyStateIcon(Heroicon::OutlinedKey);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create API key')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->placeholder('e.g. Claude Desktop')
                        ->required()
                        ->maxLength(60),
                    Radio::make('ability')
                        ->label('Access')
                        ->options([
                            'read' => 'Read only',
                            'full' => 'Full access',
                        ])
                        ->descriptions([
                            'read' => 'List and read posts and comments. No changes.',
                            'full' => 'Create, edit, approve, and delete posts and comments.',
                        ])
                        ->default('read')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $abilities = $data['ability'] === 'full'
                        ? ['posts:read', 'posts:write', 'comments:read', 'comments:write']
                        : ['posts:read', 'comments:read'];

                    $token = auth()->user()->createToken($data['name'], $abilities);

                    // The plaintext is shown inline on the page (see api-keys view).
                    // Opening a second modal from here breaks the Livewire request
                    // with "Property [$mountedActionSchema0] not found".
                    $this->newTokenPlaintext = $token->accessToken;
                    $this->newTokenName = $data['name'];
                }),
        ];
    }

    public function dismissNewToken(): void
    {
        $this->newTokenPlaintext = null;
        $this->newTokenName = null;
    }
}
