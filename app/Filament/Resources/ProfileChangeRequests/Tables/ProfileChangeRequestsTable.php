<?php

namespace App\Filament\Resources\ProfileChangeRequests\Tables;

use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Office;
use App\Models\ProfileChangeRequest;
use App\Services\ProfileChangeWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProfileChangeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('personnel.full_name_last_first')
                    ->label('Employee')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'personnel',
                        fn (Builder $query) => $query
                            ->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('employee_no', 'like', "%{$search}%"),
                    ))
                    ->wrap(),
                TextColumn::make('items_count')
                    ->label('Fields')
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('verification')
                    ->label('Verified')
                    ->state(fn (ProfileChangeRequest $record) => $record->isVerified() ? 'Yes' : 'Not yet')
                    ->badge()
                    ->color(fn (ProfileChangeRequest $record) => $record->isVerified() ? 'success' : 'gray'),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Filed by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RequestStatus::class)
                    ->multiple(),
                SelectFilter::make('office')
                    ->label('Office')
                    ->options(fn () => Office::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $officeId) => $query->whereHas(
                            'personnel.activePrimaryAppointment',
                            fn (Builder $query) => $query->where('office_id', $officeId),
                        ),
                    )),
                SelectFilter::make('awaiting')
                    ->label('Awaiting')
                    ->options([
                        'verification' => 'HR Staff verification',
                        'decision' => 'HR Approver decision',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'verification' => $query->pending()->whereNull('reviewed_at'),
                        'decision' => $query->pending()->whereNotNull('reviewed_at'),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(self::workflowActions())
                    ->label('Workflow')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->button()
                    ->hidden(fn (ProfileChangeRequest $record) => $record->status->isFinal()),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /** @return array<Action> */
    private static function workflowActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit for review')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Once submitted, the request goes to HR Staff for verification and can no longer be edited.')
                ->visible(fn (ProfileChangeRequest $record) => auth()->user()?->can('submit', $record))
                ->action(function (ProfileChangeRequest $record, ProfileChangeWorkflow $workflow) {
                    $workflow->submit($record, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Request submitted')
                        ->body("{$record->reference_no} is now pending HR verification.")
                        ->send();
                }),

            Action::make('verify')
                ->label('Verify')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->schema([
                    Textarea::make('remarks')
                        ->label('Verification remarks')
                        ->placeholder('Documents checked against the 201 file.')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (ProfileChangeRequest $record) => auth()->user()?->can('verify', $record))
                ->action(function (ProfileChangeRequest $record, array $data, ProfileChangeWorkflow $workflow) {
                    $workflow->verify($record, auth()->user(), $data['remarks'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Request verified')
                        ->body('It is now with the HR Approver for a decision.')
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Approving applies every requested change to the personnel record.')
                ->schema([
                    Textarea::make('remarks')->label('Decision remarks')->rows(3)->maxLength(1000),
                ])
                ->visible(fn (ProfileChangeRequest $record) => auth()->user()?->can('decide', $record))
                ->action(function (ProfileChangeRequest $record, array $data, ProfileChangeWorkflow $workflow) {
                    try {
                        $workflow->approve($record, auth()->user(), $data['remarks'] ?? null);
                    } catch (BusinessRuleViolation $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Business rule violated')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Request approved')
                        ->body('The personnel record has been updated.')
                        ->send();
                }),

            Action::make('return')
                ->label('Return for revision')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->schema([
                    Textarea::make('remarks')
                        ->label('What must the employee correct?')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (ProfileChangeRequest $record) => auth()->user()?->can('returnForRevision', $record))
                ->action(function (ProfileChangeRequest $record, array $data, ProfileChangeWorkflow $workflow) {
                    $workflow->returnForRevision($record, auth()->user(), $data['remarks']);

                    Notification::make()
                        ->success()
                        ->title('Request returned')
                        ->body('The employee can now edit and resubmit it.')
                        ->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('remarks')
                        ->label('Reason for rejection')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (ProfileChangeRequest $record) => auth()->user()?->can('decide', $record))
                ->action(function (ProfileChangeRequest $record, array $data, ProfileChangeWorkflow $workflow) {
                    $workflow->reject($record, auth()->user(), $data['remarks']);

                    Notification::make()
                        ->success()
                        ->title('Request rejected')
                        ->send();
                }),
        ];
    }
}
