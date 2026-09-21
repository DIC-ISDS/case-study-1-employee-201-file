<?php

namespace App\Filament\Resources\Positions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('salary_grade')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(33),
                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }
}
