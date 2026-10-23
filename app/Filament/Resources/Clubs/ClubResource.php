<?php

namespace App\Filament\Resources\Clubs;

use App\Filament\Resources\Clubs\Pages\ManageClubs;
use App\Models\Club;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static ?string $slug = 'verenigingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'vereniging';

    protected static ?string $pluralModelLabel = 'verenigingen';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 80;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Toggle::make('pays_collectively')
                    ->label('Vereniging betaalt de contributie')
                    ->helperText('Leden van deze vereniging krijgen geen eigen betaalverzoek. Bij de verlenging wachten zij op betaling door de vereniging; daarna activeert u ze in één keer.'),
                Toggle::make('is_signup_club')
                    ->label('Vereniging voor het aanmeldformulier')
                    ->helperText('Iedereen die zich via de website aanmeldt, wordt lid via deze vereniging. Er kan maar één zijn.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('pays_collectively')
                    ->label('Betaalt contributie')
                    ->boolean(),
                IconColumn::make('is_signup_club')
                    ->label('Aanmeldformulier')
                    ->boolean(),
                TextColumn::make('members_count')
                    ->label('Leden')
                    ->counts(['members' => fn ($query) => $query->current()])
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClubs::route('/'),
        ];
    }
}
