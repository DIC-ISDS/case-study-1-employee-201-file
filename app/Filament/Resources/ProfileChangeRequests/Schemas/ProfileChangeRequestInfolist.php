<?php

namespace App\Filament\Resources\ProfileChangeRequests\Schemas;

use App\Models\ProfileChangeRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfileChangeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('reference_no')->label('Reference'),
                        TextEntry::make('personnel.full_name')->label('Employee'),
                        TextEntry::make('personnel.employee_no')->label('Employee no.'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('purpose')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Requested changes')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                TextEntry::make('field_label')->label('Field'),
                                TextEntry::make('old_display')->label('Current value'),
                                TextEntry::make('new_display')
                                    ->label('Requested value')
                                    ->weight('bold')
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Review and decision')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not yet submitted'),
                        TextEntry::make('submitter.name')->label('Submitted by')->placeholder('—'),
                        TextEntry::make('reviewer.name')
                            ->label('Verified by (HR Staff)')
                            ->placeholder('Awaiting verification'),
                        TextEntry::make('reviewed_at')
                            ->label('Verified at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('review_remarks')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('decider.name')
                            ->label('Decided by (HR Approver)')
                            ->placeholder('Awaiting decision'),
                        TextEntry::make('decided_at')
                            ->label('Decided at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('decision_remarks')->placeholder('—')->columnSpanFull(),
                    ])
                    ->visible(fn (ProfileChangeRequest $record) => $record->submitted_at !== null),

                Section::make('Record trail')
                    ->description('Captured automatically from the signed-in user; neither field is editable.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('creator.name')->label('Filed by')->placeholder('—'),
                        TextEntry::make('created_at')->label('Filed at')->dateTime('d M Y H:i'),
                        TextEntry::make('updater.name')->label('Last acted on by')->placeholder('Never updated'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not deleted'),
                    ]),
            ]);
    }
}
