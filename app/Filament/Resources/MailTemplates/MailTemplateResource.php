<?php

namespace App\Filament\Resources\MailTemplates;

use App\Filament\Resources\MailTemplates\Pages\EditMailTemplate;
use App\Filament\Resources\MailTemplates\Pages\ListMailTemplates;
use App\Models\MailTemplate;
use App\Services\MailTemplates;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MailTemplateResource extends Resource
{
    protected static ?string $model = MailTemplate::class;

    protected static ?string $slug = 'e-mailteksten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'e-mailtekst';

    protected static ?string $pluralModelLabel = 'e-mailteksten';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 85;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('subject')
                            ->label('Onderwerp')
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('body')
                            ->label('Bericht')
                            ->required()
                            ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]),
                    ]),
                Section::make('Invulvelden')
                    ->description('Deze worden bij het versturen vervangen door de gegevens van het lid.')
                    ->columnSpan(1)
                    ->schema(fn (?MailTemplate $record): array => collect(MailTemplates::placeholders($record?->key))
                        ->map(fn (string $label, string $placeholder) => Text::make("{$placeholder} — {$label}"))
                        ->values()
                        ->all()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('E-mail'),
                TextColumn::make('subject')
                    ->label('Onderwerp'),
                TextColumn::make('updated_at')
                    ->label('Laatst gewijzigd')
                    ->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailTemplates::route('/'),
            'edit' => EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
