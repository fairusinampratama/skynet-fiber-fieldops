<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-folder';
    protected static ?string $modelLabel = 'Proyek';
    protected static ?string $pluralModelLabel = 'Proyek';
    protected static ?string $navigationLabel = 'Proyek';

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(50)->unique(ignoreRecord: true),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif', 'paused' => 'Dijeda', 'completed' => 'Selesai'])->required(),
            Forms\Components\DatePicker::make('start_date')->label('Tanggal Mulai'),
            Forms\Components\DatePicker::make('target_date')->label('Target Selesai'),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'paused' => 'Dijeda',
                        'completed' => 'Selesai',
                        default => $state ?? '-',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('areas_count')->counts('areas')->label('Area'),
                Tables\Columns\TextColumn::make('target_date')->label('Target Selesai')->date()->sortable(),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
