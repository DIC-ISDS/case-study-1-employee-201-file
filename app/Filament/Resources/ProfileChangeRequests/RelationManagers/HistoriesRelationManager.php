<?php

namespace App\Filament\Resources\ProfileChangeRequests\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only audit trail. Rows are written by ProfileChangeWorkflow only --
 * nothing here can create, edit or delete them.
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'History';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('action')->label('Action')->weight('bold'),
                TextColumn::make('from_status')->label('From')->badge()->placeholder('—'),
                TextColumn::make('to_status')->label('To')->badge()->placeholder('—'),
                TextColumn::make('actor.name')->label('By')->placeholder('System'),
                TextColumn::make('remarks')->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
