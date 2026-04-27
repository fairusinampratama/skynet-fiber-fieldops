<?php

namespace App\Filament\Resources;

use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Filament\Resources\OdpAssetResource\Pages;
use App\Models\OdpAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OdpAssetResource extends Resource
{
    protected static ?string $model = OdpAsset::class;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Official Assets';
    protected static ?string $modelLabel = 'ODP Asset';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('area_id')->relationship('area', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('odc_asset_id')->relationship('odcAsset', 'box_id')->nullable()->searchable()->preload(),
            Forms\Components\TextInput::make('box_id')->required(),
            Forms\Components\FileUpload::make('photo_path')->image()->directory('assets/odp')->visibility('public'),
            Forms\Components\TextInput::make('latitude')->numeric()->required(),
            Forms\Components\TextInput::make('longitude')->numeric()->required(),
            Forms\Components\Select::make('core_color')->options(collect(OdpCoreColor::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))->required(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'])->required(),
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
                Tables\Columns\TextColumn::make('odcAsset.box_id')->label('ODC')->searchable(),
                Tables\Columns\TextColumn::make('box_id')->label('ODP')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('core_color')->badge(),
                Tables\Columns\BadgeColumn::make('status'),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->sortable(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('project')->relationship('project', 'name')])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
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
