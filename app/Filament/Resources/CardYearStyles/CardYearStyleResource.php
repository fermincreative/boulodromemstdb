<?php

namespace App\Filament\Resources\CardYearStyles;

use App\Filament\Resources\CardYearStyles\Pages\ManageCardYearStyles;
use App\Models\CardYearStyle;
use App\Services\CardYearStyles;
use App\Support\Color;
use BackedEnum;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CardYearStyleResource extends Resource
{
    protected static ?string $model = CardYearStyle::class;

    protected static ?string $slug = 'kaartkleuren';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $modelLabel = 'kaartkleur';

    protected static ?string $pluralModelLabel = 'kaartkleuren per jaar';

    protected static ?string $navigationLabel = 'Kaartkleuren';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 86;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('year')
                    ->label('Jaar')
                    ->integer()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn (): int => (int) (CardYearStyle::max('year') ?? today()->year) + 1)
                    ->columnSpanFull(),
                ColorPicker::make('badge_color')
                    ->label('Achtergrond jaarvak')
                    ->hex()
                    ->regex('/^#[0-9A-Fa-f]{6}$/')
                    ->default('#000000')
                    ->required()
                    ->live(),
                ColorPicker::make('text_color')
                    ->label('Kleur jaartal')
                    ->hex()
                    ->regex('/^#[0-9A-Fa-f]{6}$/')
                    ->default('#FFFFFF')
                    ->required()
                    ->live()
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $badge = $get('badge_color');

                        if (Color::isHex($badge) && Color::isHex($value) && Color::contrast($badge, $value) < CardYearStyles::MIN_CONTRAST) {
                            $fail('Het jaartal is slecht leesbaar op deze achtergrond. Kies kleuren met meer contrast.');
                        }
                    }),
                Text::make(fn (Get $get): HtmlString => self::preview($get('year'), $get('badge_color'), $get('text_color')))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Jaren zonder eigen kleuren krijgen het standaard beige jaarvak met zwarte tekst.')
            ->columns([
                TextColumn::make('year')
                    ->label('Jaar')
                    ->sortable(),
                TextColumn::make('preview')
                    ->label('Voorbeeld')
                    ->state(fn (CardYearStyle $record): HtmlString => self::preview($record->year, $record->badge_color, $record->text_color))
                    ->html(),
                ColorColumn::make('badge_color')
                    ->label('Achtergrond'),
                ColorColumn::make('text_color')
                    ->label('Jaartal'),
            ])
            ->defaultSort('year', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Kaarten van dit jaar krijgen dan weer het standaard beige jaarvak.'),
            ])
            ->headerActions([
                CreateAction::make()->label('Jaar toevoegen'),
            ])
            ->paginated(false);
    }

    /**
     * The year badge as it appears on the card.
     */
    public static function preview(mixed $year, ?string $badge, ?string $text): HtmlString
    {
        if (! Color::isHex($badge) || ! Color::isHex($text)) {
            return new HtmlString('');
        }

        return new HtmlString(sprintf(
            '<span style="display:inline-block;padding:.2rem .7rem;border-radius:.45rem;font-weight:700;font-size:1.25rem;background:%s;color:%s;border:1px solid #d4d4d8">%s</span>',
            e($badge), e($text), e($year ?: today()->year),
        ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCardYearStyles::route('/'),
        ];
    }
}
