<?php

namespace App\Filament\Resources\Personnel;

use App\Filament\Resources\Personnel\Pages\CreatePersonnel;
use App\Filament\Resources\Personnel\Pages\EditPersonnel;
use App\Filament\Resources\Personnel\Pages\ListPersonnels;
use App\Filament\Resources\Personnel\Pages\ViewPersonnel;
use App\Filament\Resources\Personnel\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Personnel\Schemas\PersonnelForm;
use App\Filament\Resources\Personnel\Schemas\PersonnelInfolist;
use App\Filament\Resources\Personnel\Tables\PersonnelsTable;
use App\Models\Personnel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PersonnelResource extends Resource
{
    protected static ?string $model = Personnel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Personnel';

    protected static ?string $modelLabel = 'personnel record';

    protected static ?string $pluralModelLabel = 'personnel';

    protected static string|null|\UnitEnum $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'employee_no';

    /**
     * Set explicitly: the resource lives in a Personnel namespace, which would
     * otherwise give the clumsy URL /admin/personnel/personnels.
     */
    protected static ?string $slug = 'personnel';

    public static function form(Schema $schema): Schema
    {
        return PersonnelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonnelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonnelsTable::configure($table);
    }

    /**
     * HR sees the whole roster; an employee sees only their own 201 file.
     * Policies gate the actions, this gates what is listed at all.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            // Trashed records stay reachable for the Trashed filter and the
            // restore action; the filter hides them by default.
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['activePrimaryAppointment.office', 'activePrimaryAppointment.position']);

        $user = Filament::auth()->user();

        if ($user !== null && ! $user->isHr()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            AppointmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonnels::route('/'),
            'create' => CreatePersonnel::route('/create'),
            'view' => ViewPersonnel::route('/{record}'),
            'edit' => EditPersonnel::route('/{record}/edit'),
        ];
    }
}
