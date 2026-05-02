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
    protected static string | UnitEnum | null $navigationGroup = 'Aset Resmi';
    protected static ?string $modelLabel = 'Aset OLT';
    protected static ?string $pluralModelLabel = 'Aset OLT';
    protected static ?string $navigationLabel = 'Aset OLT';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('project_id')->label('Proyek')->relationship('project', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('area_id')->label('Area')->relationship('area', 'name')->nullable()->searchable()->preload(),
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(100),
            Forms\Components\TextInput::make('location')->label('Lokasi')->maxLength(255),
            Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric(),
            Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric(),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif', 'inactive' => 'Tidak Aktif', 'maintenance' => 'Maintenance'])->required(),
            Forms\Components\Repeater::make('ponPorts')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('pon_number')->label('Nomor PON')->numeric()->minValue(1)->maxValue(128)->required(),
                    Forms\Components\TextInput::make('label')->label('Label')->maxLength(100),
                    Forms\Components\TextInput::make('capacity')->label('Kapasitas')->numeric()->minValue(1)->default(128)->required(),
                    Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif', 'inactive' => 'Tidak Aktif', 'maintenance' => 'Maintenance'])->required(),
                ])
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')->label('Proyek')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('area.name')->label('Area')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pon_ports_count')->counts('ponPorts')->label('PON'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'maintenance' => 'Maintenance',
                        default => $state ?? '-',
                    })
                    ->badge(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('project')->label('Proyek')->relationship('project', 'name')])
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
