<?php

namespace App\Filament\Resources\ProfileChangeRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\ProfileChangeRequests\Pages\CreateProfileChangeRequest;
use App\Filament\Resources\ProfileChangeRequests\Pages\EditProfileChangeRequest;
use App\Filament\Resources\ProfileChangeRequests\Pages\ListProfileChangeRequests;
use App\Filament\Resources\ProfileChangeRequests\Pages\ViewProfileChangeRequest;
use App\Filament\Resources\ProfileChangeRequests\RelationManagers\HistoriesRelationManager;
use App\Filament\Resources\ProfileChangeRequests\Schemas\ProfileChangeRequestForm;
use App\Filament\Resources\ProfileChangeRequests\Schemas\ProfileChangeRequestInfolist;
use App\Filament\Resources\ProfileChangeRequests\Tables\ProfileChangeRequestsTable;
use App\Models\ProfileChangeRequest;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProfileChangeRequestResource extends Resource
{
    protected static ?string $model = ProfileChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Profile Change Requests';

    protected static ?string $modelLabel = 'profile change request';

    protected static string|null|\UnitEnum $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        return ProfileChangeRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProfileChangeRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProfileChangeRequestsTable::configure($table);
    }

    /** An employee sees only the requests raised for their own record. */
    public static function getEloquentQuery(): Builder
    {
        // Trashed requests stay reachable for the Trashed filter and the
        // restore action; the filter hides them by default.
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with('personnel');

        $user = Filament::auth()->user();

        if ($user !== null && ! $user->isHr()) {
            $query->where(fn (Builder $query) => $query
                ->where('submitted_by', $user->id)
                ->orWhereHas('personnel', fn (Builder $query) => $query->where('user_id', $user->id)));
        }

        return $query;
    }

    /** Badge the nav with whatever is waiting on the signed-in role. */
    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return null;
        }

        // withoutTrashed(): the resource query deliberately keeps trashed
        // records reachable, but a deleted request is not waiting on anyone.
        $query = static::getEloquentQuery()
            ->withoutTrashed()
            ->where('status', RequestStatus::Pending);

        if ($user->isHrStaff()) {
            $query->whereNull('reviewed_at');
        } elseif ($user->isHrApprover()) {
            $query->whereNotNull('reviewed_at');
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRelations(): array
    {
        return [
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileChangeRequests::route('/'),
            'create' => CreateProfileChangeRequest::route('/create'),
            'view' => ViewProfileChangeRequest::route('/{record}'),
            'edit' => EditProfileChangeRequest::route('/{record}/edit'),
        ];
    }
}
