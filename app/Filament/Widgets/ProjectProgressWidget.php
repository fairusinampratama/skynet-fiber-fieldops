<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ProjectProgressWidget extends TableWidget
{
    protected static ?string $heading = 'Project Progress';

    public function table(Table $table): Table
    {
        return $table
            ->query(Project::query()->withCount(['teams', 'areas']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\BadgeColumn::make('status'),
                Tables\Columns\TextColumn::make('teams_count')->label('Teams'),
                Tables\Columns\TextColumn::make('areas_count')->label('Areas'),
                Tables\Columns\TextColumn::make('odc_assets_count')->counts('odcAssets')->label('ODC'),
                Tables\Columns\TextColumn::make('odp_assets_count')->counts('odpAssets')->label('ODP'),
            ]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
