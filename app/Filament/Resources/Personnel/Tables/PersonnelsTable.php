<?php

namespace App\Filament\Resources\Personnel\Tables;

use App\Enums\EmploymentStatus;
use App\Models\Office;
use App\Models\Position;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonnelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_no')
                    ->label('Employee no.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name_last_first')
                    ->label('Name')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('last_name', $direction)
                        ->orderBy('first_name', $direction)),
                TextColumn::make('activePrimaryAppointment.office.name')
                    ->label('Office')
                    ->placeholder('Unassigned')
                    ->wrap(),
                TextColumn::make('activePrimaryAppointment.position.title')
                    ->label('Position')
                    ->placeholder('Unassigned')
                    ->wrap(),
                TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_number')
                    ->label('Contact')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Encoded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updater.name')
                    ->label('Last updated by')
                    ->placeholder('Never updated')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('office')
                    ->label('Office')
                    ->options(fn () => Office::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $officeId) => $query->whereHas(
                            'activePrimaryAppointment',
                            fn (Builder $query) => $query->where('office_id', $officeId),
                        ),
                    )),
                SelectFilter::make('position')
                    ->label('Position')
                    ->options(fn () => Position::orderBy('title')->pluck('title', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $positionId) => $query->whereHas(
                            'activePrimaryAppointment',
                            fn (Builder $query) => $query->where('position_id', $positionId),
                        ),
                    )),
                SelectFilter::make('employment_status')
                    ->label('Employment status')
                    ->options(EmploymentStatus::class)
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
