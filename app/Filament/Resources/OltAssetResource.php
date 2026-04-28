<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltAssetResource\Pages;
use App\Models\OltAsset;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class OltAssetResource extends Resource
{
    protected static ?string $model = OltAsset::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-signal';
    protected static string | UnitEnum | null $navigationGroup = 'Official Assets';
    protected static ?string $modelLabel = 'OLT Asset';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('area_id')->relationship('area', 'name')->nullable()->searchable()->preload(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->required()->maxLength(100),
            Forms\Components\TextInput::make('location')->maxLength(255),
            Forms\Components\TextInput::make('latitude')->numeric(),
            Forms\Components\TextInput::make('longitude')->numeric(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'])->required(),
            Forms\Components\Repeater::make('ponPorts')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('pon_number')->numeric()->minValue(1)->maxValue(128)->required(),
                    Forms\Components\TextInput::make('label')->maxLength(100),
                    Forms\Components\TextInput::make('capacity')->numeric()->minValue(1)->default(128)->required(),
                    Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'])->required(),
                ])
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('area.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pon_ports_count')->counts('ponPorts')->label('PON'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('project')->relationship('project', 'name')])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOltAssets::route('/'),
            'create' => Pages\CreateOltAsset::route('/create'),
            'edit' => Pages\EditOltAsset::route('/{record}/edit'),
        ];
    }
}
