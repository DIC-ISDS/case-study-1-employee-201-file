<?php

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Office;
use App\Models\Position;
use App\Services\AppointmentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Appointment history';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('office_id')
                    ->label('Office')
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('position_id')
                    ->label('Position')
                    ->relationship('position', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->options(AppointmentType::class)
                    ->default(AppointmentType::Primary)
                    ->native(false)
                    ->required()
                    ->helperText('Only one PRIMARY appointment may be ACTIVE at a time.'),
                Select::make('status')
                    ->options(AppointmentStatus::class)
                    ->default(AppointmentStatus::Active)
                    ->native(false)
                    ->required(),
                DatePicker::make('start_date')
                    ->default(now())
                    ->required(),
                DatePicker::make('end_date')
                    ->afterOrEqual('start_date'),
                TextInput::make('remarks')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            // Trashed appointments stay reachable so the Trashed filter and
            // the restore action can find them.
            ->modifyQueryUsing(fn ($query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->columns([
                TextColumn::make('position.title')->label('Position')->wrap(),
                TextColumn::make('office.name')->label('Office')->wrap(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('start_date')->label('Start')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->label('End')->date('d M Y')->placeholder('—'),
                TextColumn::make('remarks')->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                // The proper way to move an employee: ends the current primary
                // appointment and opens the new one in a single transaction.
                Action::make('transfer')
                    ->label('Transfer primary')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->schema([
                        Select::make('office_id')
                            ->label('New office')
                            ->options(fn () => Office::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('position_id')
                            ->label('New position')
                            ->options(fn () => Position::orderBy('title')->pluck('title', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('remarks')->maxLength(255),
                    ])
                    ->action(function (array $data, AppointmentService $appointments) {
                        $appointments->transferPrimary(
                            $this->getOwnerRecord(),
                            (int) $data['office_id'],
                            (int) $data['position_id'],
                            $data['remarks'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Primary appointment transferred')
                            ->body('The previous appointment was ended and the new one is now active.')
                            ->send();
                    }),

                // Adding an appointment directly is what trips the business
                // rule when one is already active and primary.
                CreateAction::make()
                    ->label('Add appointment')
                    ->using(function (array $data, string $model) {
                        try {
                            return $this->getRelationship()->create($data);
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Business rule violated')
                                ->body($exception->getMessage())
                                ->persistent()
                                ->send();

                            return null;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Appointment $record, array $data) {
                        try {
                            $record->update($data);
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Business rule violated')
                                ->body($exception->getMessage())
                                ->persistent()
                                ->send();
                        }

                        return $record;
                    }),
                Action::make('end')
                    ->label('End')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This closes the appointment and frees the employee to receive a new primary appointment.')
                    ->visible(fn (Appointment $record) => $record->status === AppointmentStatus::Active)
                    ->action(function (Appointment $record) {
                        $record->update([
                            'status' => AppointmentStatus::Ended,
                            'end_date' => now()->toDateString(),
                        ]);

                        Notification::make()->success()->title('Appointment ended')->send();
                    }),

                // Ending an appointment is the HR act; deleting one is for a
                // row that should never have been encoded. It soft-deletes,
                // so the history stays intact.
                DeleteAction::make(),

                // Restoring reopens the appointment, so the one-active-primary
                // rule is checked again on the way back in.
                RestoreAction::make()
                    ->using(function (Appointment $record) {
                        try {
                            $record->restore();
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Business rule violated')
                                ->body($exception->getMessage())
                                ->persistent()
                                ->send();
                        }

                        return $record;
                    }),
            ]);
    }
}
