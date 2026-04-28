<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltPonPortResource\Pages;
use App\Models\OltPonPort;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class OltPonPortResource extends Resource
{
    protected static ?string $model = OltPonPort::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static string | UnitEnum | null $navigationGroup = 'Official Assets';
    protected static ?string $modelLabel = 'OLT PON Port';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('olt_asset_id')->relationship('oltAsset', 'code')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('pon_number')->numeric()->minValue(1)->maxValue(128)->required(),
            Forms\Components\TextInput::make('label')->maxLength(100),
            Forms\Components\TextInput::make('capacity')->numeric()->minValue(1)->default(128)->required(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('oltAsset.project.name')->label('Project')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('oltAsset.code')->label('OLT')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('pon_number')->label('PON')->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('capacity')->sortable(),
                Tables\Columns\TextColumn::make('odc_assets_count')->counts('odcAssets')->label('ODC'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('olt')->relationship('oltAsset', 'code')])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOltPonPorts::route('/'),
            'create' => Pages\CreateOltPonPort::route('/create'),
            'edit' => Pages\EditOltPonPort::route('/{record}/edit'),
        ];
    }
}
