<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CookieSettingResource\Pages\CreateCookieSetting;
use App\Filament\Resources\CookieSettingResource\Pages\EditCookieSetting;
use App\Filament\Resources\CookieSettingResource\Pages\ListCookieSettings;
use App\Models\CookieSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The texts and the on/off switch of the cookie dialog.
 *
 * The table is a singleton: the dialog reads one record, so the resource only edits it and never lets a
 * second one be created. Two values deserve care:
 *
 * - `version` is the consent version the browser stores next to the chosen categories. Raising it
 *   invalidates every stored choice and asks every visitor again, so it changes only when the wording
 *   or the set of cookies really changed.
 * - the category descriptions are shown exactly as written, and a category the site has no script for
 *   is not offered by the dialog at all - it is the tracking scripts, not this text, that decide what
 *   can be agreed with.
 */
class CookieSettingResource extends Resource
{
    protected static ?string $model = CookieSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'Cookie nastavení';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return 'Cookie nastavení';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Cookie nastavení';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Změna textu se projeví na webu okamžitě')
                ->description('Cookie lišta čte tento záznam při každém načtení stránky. Zvýšení verze souhlasu zneplatní uložené volby v prohlížečích a návštěvníci dostanou lištu znovu; zvyšujte ji proto jen při skutečné změně textu nebo používaných cookies.')
                ->warning()
                ->columnSpanFull(),
            Section::make('Základní nastavení')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Cookie lišta zapnuta')
                        ->default(true),
                    TextInput::make('version')
                        ->label('Verze souhlasu')
                        ->helperText('Například 1.0. Změna verze znamená nové dotázání návštěvníků.')
                        ->required()
                        ->maxLength(64)
                        ->default(CookieSetting::DEFAULT_VERSION),
                    TextInput::make('footer_link_label')
                        ->label('Popisek odkazu v patičce')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(3)
                ->columnSpanFull(),
            Section::make('Text lišty')
                ->schema([
                    TextInput::make('banner_title')
                        ->label('Titulek lišty')
                        ->maxLength(255),
                    TextInput::make('accept_all_label')
                        ->label('Tlačítko „povolit vše"')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('banner_description')
                        ->label('Popis v liště')
                        ->helperText('Napište, co se ukládá bez souhlasu a co jen s ním.')
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('reject_all_label')
                        ->label('Tlačítko „odmítnout volitelné"')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('customize_label')
                        ->label('Tlačítko „nastavit"')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('save_preferences_label')
                        ->label('Tlačítko „uložit nastavení"')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Kategorie')
                ->schema([
                    TextInput::make('necessary_title')
                        ->label('Nezbytné – titulek')
                        ->maxLength(255),
                    Textarea::make('necessary_description')
                        ->label('Nezbytné – popis')
                        ->rows(2),
                    TextInput::make('analytics_title')
                        ->label('Analytické – titulek')
                        ->maxLength(255),
                    Textarea::make('analytics_description')
                        ->label('Analytické – popis')
                        ->rows(2),
                    TextInput::make('marketing_title')
                        ->label('Marketingové – titulek')
                        ->maxLength(255),
                    Textarea::make('marketing_description')
                        ->label('Marketingové – popis')
                        ->rows(2),
                    TextInput::make('preferences_title')
                        ->label('Preferenční – titulek')
                        ->maxLength(255),
                    Textarea::make('preferences_description')
                        ->label('Preferenční – popis')
                        ->rows(2),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Odkazy na právní dokumenty')
                ->schema([
                    TextInput::make('privacy_policy_url')
                        ->label('Adresa zásad ochrany osobních údajů')
                        ->helperText('Volitelné. Prázdné pole znamená odkaz na stránku /ochrana-osobnich-udalu, absolutní adresu použijte jen při přesunu dokumentu jinam.')
                        ->maxLength(255),
                    TextInput::make('cookie_policy_url')
                        ->label('Adresa zásad cookies')
                        ->helperText('Volitelné. Prázdné pole znamená odkaz na stránku /zasady-cookies.')
                        ->maxLength(255),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('enabled')
                    ->label('Zapnuto')
                    ->boolean(),
                TextColumn::make('version')
                    ->label('Verze')
                    ->badge(),
                TextColumn::make('banner_title')
                    ->label('Titulek lišty')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    /**
     * The dialog reads one record, so a second one is never created from the panel.
     */
    public static function canCreate(): bool
    {
        return ! CookieSetting::query()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCookieSettings::route('/'),
            'create' => CreateCookieSetting::route('/create'),
            'edit' => EditCookieSetting::route('/{record}/edit'),
        ];
    }
}
