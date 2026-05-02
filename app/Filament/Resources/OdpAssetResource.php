<?php

namespace App\Filament\Resources;

use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Filament\Resources\OdpAssetResource\Pages;
use App\Models\OdpAsset;
use App\Models\OltAsset;
use App\Models\OltPonPort;
use App\Support\FieldopsPhotoProcessor;
use App\Support\FieldopsPhotoUpload;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OdpAssetResource extends Resource
{
    protected static ?string $model = OdpAsset::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static string | UnitEnum | null $navigationGroup = 'Aset Resmi';
    protected static ?string $modelLabel = 'Aset ODP';
    protected static ?string $pluralModelLabel = 'Aset ODP';
    protected static ?string $navigationLabel = 'Aset ODP';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('project_id')->label('Proyek')->relationship('project', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('area_id')->label('Area')->relationship('area', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('odc_asset_id')->label('ODC Induk')->relationship('odcAsset', 'box_id')->nullable()->searchable()->preload(),
            Forms\Components\TextInput::make('box_id')->label('Box ID')->required(),
            FieldopsPhotoUpload::configure(Forms\Components\FileUpload::make('photo_path'))->label('Foto')->directory('assets/odp')->visibility('public'),
            Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric()->required(),
            Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric()->required(),
            Forms\Components\Select::make('core_color')->label('Warna Core')->options(collect(OdpCoreColor::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))->required(),
            Forms\Components\Select::make('status')->label('Status')->options(static::statusOptions())->required(),
            Forms\Components\Repeater::make('ports')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('port_number')->label('Nomor Port')->options(array_combine(range(1, 8), range(1, 8)))->required(),
                    Forms\Components\Select::make('status')->label('Status')->options(PortStatus::simpleOptions())->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->state(fn (?OdpAsset $record): ?string => FieldopsPhotoProcessor::tableThumbnailPathFor($record?->photo_path))
                    ->visibility('public')
                    ->imageSize(48)
                    ->square(),
                Tables\Columns\TextColumn::make('project.name')->label('Proyek')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('area.name')->label('Area')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('odcAsset.oltPonPort.oltAsset.code')->label('OLT')->placeholder('Belum Mapping')->searchable(),
                Tables\Columns\TextColumn::make('odcAsset.oltPonPort.pon_number')->label('PON')->placeholder('-'),
                Tables\Columns\TextColumn::make('odcAsset.box_id')->label('ODC Induk')->searchable(),
                Tables\Columns\TextColumn::make('box_id')->label('ODP')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('core_color')->label('Warna Core')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state)),
                Tables\Columns\TextColumn::make('sourceSubmission.submitted_at')->label('Diajukan Pada')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('approved_at')->label('Disetujui Pada')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')->label('Proyek')->relationship('project', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('area')->label('Area')->relationship('area', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('odc')->label('ODC Induk')->relationship('odcAsset', 'box_id')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('olt')
                    ->label('OLT')
                    ->options(fn (): array => OltAsset::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('odcAsset.oltPonPort', fn (Builder $query): Builder => $query->where('olt_asset_id', $data['value']))),
                Tables\Filters\SelectFilter::make('pon')
                    ->label('PON')
                    ->options(fn (): array => OltPonPort::query()
                        ->with('oltAsset')
                        ->orderBy('olt_asset_id')
                        ->orderBy('pon_number')
                        ->get()
                        ->mapWithKeys(fn (OltPonPort $port): array => [$port->id => "{$port->oltAsset->code} / PON {$port->pon_number}"])
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('odcAsset', fn (Builder $query): Builder => $query->where('olt_pon_port_id', $data['value']))),
                Tables\Filters\SelectFilter::make('core_color')->label('Warna Core')->options(
                    collect(OdpCoreColor::cases())->mapWithKeys(fn (OdpCoreColor $case): array => [$case->value => $case->getLabel()])->all(),
                ),
                Tables\Filters\TernaryFilter::make('mapping_state')
                    ->label('Mapping ODC')
                    ->trueLabel('Terhubung ODC')
                    ->falseLabel('Belum Mapping')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('odc_asset_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('odc_asset_id'),
                    ),
                Tables\Filters\TernaryFilter::make('photo_state')
                    ->label('Foto')
                    ->trueLabel('Ada Foto')
                    ->falseLabel('Tanpa Foto')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('photo_path'),
                        false: fn (Builder $query): Builder => $query->whereNull('photo_path'),
                    ),
            ])
            ->defaultSort('approved_at', 'desc')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'maintenance' => 'Maintenance',
        ];
    }

    public static function statusLabel(?string $state): string
    {
        return static::statusOptions()[$state] ?? $state ?? '-';
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'active' => 'success',
            'inactive' => 'danger',
            'maintenance' => 'warning',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdpAssets::route('/'),
            'create' => Pages\CreateOdpAsset::route('/create'),
            'edit' => Pages\EditOdpAsset::route('/{record}/edit'),
        ];
    }
}
