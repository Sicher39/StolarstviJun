<?php

namespace App\Filament\Resources\LegalDocumentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only history of what the site published. The snapshots are written by the model, so this
 * table only displays them: there is no way to edit or delete a revision from the panel.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Historie zveřejnění';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('version')
                    ->label('Verze')
                    ->badge(),
                TextColumn::make('title')
                    ->label('Název')
                    ->limit(60),
                TextColumn::make('published_at')
                    ->label('Zveřejněno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                TextColumn::make('effective_from')
                    ->label('Účinnost od')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label('Uložil (ID uživatele)')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uloženo')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
