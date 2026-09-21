<?php

namespace App\Filament\Resources\Personnel\Schemas;

use App\Enums\EmploymentStatus;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PersonnelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('Core 201 file information.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('employee_no')
                            ->label('Employee no.')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('last_name')->required()->maxLength(255),
                        TextInput::make('first_name')->required()->maxLength(255),
                        TextInput::make('middle_name')->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Date of birth')
                            ->maxDate(now()),
                        Select::make('employment_status')
                            ->options(EmploymentStatus::class)
                            ->default(EmploymentStatus::Permanent)
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Contact')
                    ->columns(3)
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('contact_number')->tel()->maxLength(255),
                        TextInput::make('address')->maxLength(255),
                    ]),

                Section::make('System access')
                    ->description('Link this employee to a login so they can file their own profile change requests.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Linked user account')
                            ->relationship('user', 'name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->email})")
                            ->searchable()
                            ->preload()
                            ->unique(ignoreRecord: true)
                            ->helperText('Each login may be linked to at most one personnel record.'),
                    ]),
            ]);
    }
}
