<?php

namespace App\Filament\Resources\ProfileChangeRequests\Schemas;

use App\Enums\EmploymentStatus;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Support\ProfileField;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProfileChangeRequestForm
{
    /** Fields whose value is chosen from a list rather than typed. */
    private const LOOKUP_FIELDS = ['office_id', 'position_id', 'employment_status'];

    public static function configure(Schema $schema): Schema
    {
        $user = Filament::auth()->user();
        $ownPersonnelId = $user?->personnel()->value('id');

        return $schema
            ->components([
                Section::make('Request')
                    ->columns(2)
                    ->schema([
                        Select::make('personnel_id')
                            ->label('Employee')
                            ->options(fn () => Personnel::active()
                                ->orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (Personnel $p) => [$p->id => "{$p->full_name_last_first} ({$p->employee_no})"]))
                            ->searchable()
                            ->required()
                            // An employee may only file against their own 201 file.
                            ->default($ownPersonnelId)
                            ->disabled(fn () => $user !== null && ! $user->isHr())
                            ->dehydrated(),
                        Textarea::make('purpose')
                            ->label('Purpose / justification')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Requested changes')
                    ->description('Add one line per field you want changed. The current value is recorded automatically when the line is saved.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Add a field to change')
                            ->minItems(1)
                            ->columns(2)
                            ->itemLabel(fn (array $state) => isset($state['field'])
                                ? ProfileField::label($state['field'])
                                : null)
                            // The value is entered through one of two inputs
                            // depending on the field chosen. They need separate
                            // state paths -- two components sharing one path
                            // overwrite each other -- so they are collapsed back
                            // into new_value on the way to the database, and
                            // expanded again when an existing request is loaded.
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => $data + [
                                'new_value_option' => $data['new_value'] ?? null,
                                'new_value_text' => $data['new_value'] ?? null,
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::normaliseItem($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::normaliseItem($data))
                            ->schema([
                                Select::make('field')
                                    ->label('Field')
                                    ->options(ProfileField::all())
                                    ->required()
                                    ->live()
                                    // One line per field; the table carries a
                                    // unique index on (request, field) too.
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                Select::make('new_value_option')
                                    ->label('New value')
                                    ->options(fn (Get $get) => match ($get('field')) {
                                        'office_id' => Office::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
                                        'position_id' => Position::where('is_active', true)->orderBy('title')->pluck('title', 'id')->all(),
                                        'employment_status' => collect(EmploymentStatus::cases())
                                            ->mapWithKeys(fn (EmploymentStatus $case) => [$case->value => $case->getLabel()])
                                            ->all(),
                                        default => [],
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->required(fn (Get $get) => self::isLookup($get('field')))
                                    ->visible(fn (Get $get) => self::isLookup($get('field'))),

                                TextInput::make('new_value_text')
                                    ->label('New value')
                                    ->maxLength(255)
                                    ->required(fn (Get $get) => $get('field') !== null && ! self::isLookup($get('field')))
                                    ->visible(fn (Get $get) => $get('field') !== null && ! self::isLookup($get('field'))),
                            ]),
                    ]),
            ]);
    }

    private static function isLookup(?string $field): bool
    {
        return $field !== null && in_array($field, self::LOOKUP_FIELDS, true);
    }

    /** Collapse the two value inputs back into the single stored column. */
    private static function normaliseItem(array $data): array
    {
        $data['new_value'] = self::isLookup($data['field'] ?? null)
            ? ($data['new_value_option'] ?? null)
            : ($data['new_value_text'] ?? null);

        unset($data['new_value_option'], $data['new_value_text']);

        return $data;
    }
}
