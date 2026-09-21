<?php

namespace App\Filament\Resources\Personnel\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PersonnelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('employee_no')->label('Employee no.'),
                        TextEntry::make('full_name')->label('Name'),
                        TextEntry::make('employment_status')->badge(),
                        TextEntry::make('birth_date')->date('d M Y')->placeholder('—'),
                        TextEntry::make('email'),
                        TextEntry::make('contact_number')->placeholder('—'),
                        TextEntry::make('address')->placeholder('—')->columnSpan(2),
                    ]),

                Section::make('Current primary appointment')
                    ->description('An employee may hold only one active primary appointment at a time.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('activePrimaryAppointment.position.title')
                            ->label('Position')
                            ->placeholder('No active appointment'),
                        TextEntry::make('activePrimaryAppointment.office.name')
                            ->label('Office')
                            ->placeholder('No active appointment'),
                        TextEntry::make('activePrimaryAppointment.start_date')
                            ->label('Since')
                            ->date('d M Y')
                            ->placeholder('—'),
                    ]),

                Section::make('Record trail')
                    ->description('Captured automatically from the signed-in user; neither field is editable.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('creator.name')->label('Encoded by')->placeholder('—'),
                        TextEntry::make('created_at')->label('Encoded at')->dateTime('d M Y H:i'),
                        TextEntry::make('updater.name')->label('Last updated by')->placeholder('Never updated'),
                        TextEntry::make('updated_at')->label('Last updated at')->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
