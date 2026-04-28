<?php

namespace App\Filament\Resources;

use App\Enums\PortStatus;
use App\Filament\Resources\OdcAssetResource\Pages;
use App\Models\OdcAsset;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class OdcAssetResource extends Resource
{
    protected static ?string $model = OdcAsset::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';
    protected static string | UnitEnum | null $navigationGroup = 'Official Assets';
    protected static ?string $modelLabel = 'ODC Asset';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('area_id')->relationship('area', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('olt_pon_port_id')
                ->label('OLT PON')
                ->relationship('oltPonPort', 'label')
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->oltAsset->code} / PON {$record->pon_number}")
                ->nullable()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('box_id')->required(),
            Forms\Components\FileUpload::make('photo_path')->image()->directory('assets/odc')->visibility('public'),
            Forms\Components\TextInput::make('latitude')->numeric()->required(),
            Forms\Components\TextInput::make('longitude')->numeric()->required(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'unmapped' => 'Unmapped', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'])->required(),
            Forms\Components\Repeater::make('ports')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('port_number')->options(array_combine(range(1, 8), range(1, 8)))->required(),
                    Forms\Components\Select::make('status')->options(collect(PortStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('area.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('oltPonPort.oltAsset.code')->label('OLT')->placeholder('Unmapped')->searchable(),
                Tables\Columns\TextColumn::make('oltPonPort.pon_number')->label('PON')->placeholder('-'),
                Tables\Columns\TextColumn::make('box_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('latitude')->toggleable(),
                Tables\Columns\TextColumn::make('longitude')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'unmapped' => 'Unmapped', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance']),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdcAssets::route('/'),
            'create' => Pages\CreateOdcAsset::route('/create'),
            'edit' => Pages\EditOdcAsset::route('/{record}/edit'),
        ];
    }
}
