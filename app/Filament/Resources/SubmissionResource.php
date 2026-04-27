<?php

namespace App\Filament\Resources;

use App\Enums\AssetType;
use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\SubmissionResource\Pages;
use App\Models\Submission;
use App\Services\ApproveSubmissionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubmissionResource extends Resource
{
    protected static ?string $model = Submission::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['project', 'technician', 'team', 'area']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Assignment')->schema([
                Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('team_id')->relationship('team', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('area_id')->relationship('area', 'name')->required()->searchable()->preload(),
                Forms\Components\DatePicker::make('work_date')->required()->default(now()),
                Forms\Components\Hidden::make('technician_id')->default(fn () => auth()->id()),
            ])->columns(2),
            Forms\Components\Section::make('ODC Data')->schema([
                Forms\Components\TextInput::make('odc_box_id')->label('ODC Box ID')->required()->maxLength(255),
                Forms\Components\FileUpload::make('odc_photo_path')->label('ODC Photo')->image()->directory('submissions/odc')->visibility('public'),
                Forms\Components\TextInput::make('odc_latitude')->numeric()->required(),
                Forms\Components\TextInput::make('odc_longitude')->numeric()->required(),
            ])->columns(2),
            Forms\Components\Section::make('ODP Data')->schema([
                Forms\Components\TextInput::make('odp_box_id')->label('ODP Box ID')->required()->maxLength(255),
                Forms\Components\FileUpload::make('odp_photo_path')->label('ODP Photo')->image()->directory('submissions/odp')->visibility('public'),
                Forms\Components\TextInput::make('odp_latitude')->numeric()->required(),
                Forms\Components\TextInput::make('odp_longitude')->numeric()->required(),
                Forms\Components\Select::make('odp_core_color')
                    ->options(collect(OdpCoreColor::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required(),
            ])->columns(2),
            Forms\Components\Section::make('Port Availability')->schema([
                Forms\Components\Repeater::make('ports')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('asset_type')->options(['odc' => 'ODC', 'odp' => 'ODP'])->required(),
                        Forms\Components\Select::make('port_number')->options(array_combine(range(1, 8), range(1, 8)))->required(),
                        Forms\Components\Select::make('status')->options(collect(PortStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))->required(),
                    ])
                    ->columns(3)
                    ->defaultItems(16)
                    ->reorderable(false),
            ]),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
            Forms\Components\Textarea::make('review_notes')->disabled()->visible(fn (?Submission $record) => filled($record?->review_notes))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('technician.name')->sortable()->searchable()->visible(fn () => auth()->user()->isAdmin()),
                Tables\Columns\TextColumn::make('team.name')->sortable(),
                Tables\Columns\TextColumn::make('area.name')->sortable(),
                Tables\Columns\TextColumn::make('odc_box_id')->label('ODC')->searchable(),
                Tables\Columns\TextColumn::make('odp_box_id')->label('ODP')->searchable(),
                Tables\Columns\BadgeColumn::make('status'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(SubmissionStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (Submission $record) => ! auth()->user()->isAdmin() && in_array($record->status, [SubmissionStatus::Draft, SubmissionStatus::CorrectionNeeded], true))
                    ->requiresConfirmation()
                    ->action(function (Submission $record): void {
                        $record->forceFill([
                            'status' => $record->status === SubmissionStatus::CorrectionNeeded ? SubmissionStatus::Resubmitted : SubmissionStatus::Submitted,
                            'submitted_at' => now(),
                        ])->save();
                    }),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Submission $record) => auth()->user()->isAdmin() && in_array($record->status, [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted], true))
                    ->form([Forms\Components\Textarea::make('review_notes')])
                    ->action(function (Submission $record, array $data): void {
                        app(ApproveSubmissionService::class)->approve($record, auth()->user(), $data['review_notes'] ?? null);
                        Notification::make()->success()->title('Submission approved and assets updated.')->send();
                    }),
                Tables\Actions\Action::make('requestCorrection')
                    ->label('Request Correction')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Submission $record) => auth()->user()->isAdmin() && in_array($record->status, [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted], true))
                    ->form([Forms\Components\Textarea::make('review_notes')->required()])
                    ->action(fn (Submission $record, array $data) => $record->forceFill([
                        'status' => SubmissionStatus::CorrectionNeeded,
                        'review_notes' => $data['review_notes'],
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ])->save()),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Submission $record) => auth()->user()->isAdmin() && in_array($record->status, [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted], true))
                    ->form([Forms\Components\Textarea::make('review_notes')->required()])
                    ->action(fn (Submission $record, array $data) => $record->forceFill([
                        'status' => SubmissionStatus::Rejected,
                        'review_notes' => $data['review_notes'],
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ])->save()),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        if (auth()->user()->isAdmin()) {
            return true;
        }

        return $record->technician_id === auth()->id()
            && in_array($record->status, [SubmissionStatus::Draft, SubmissionStatus::CorrectionNeeded], true);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['technician_id'] = auth()->id();
        $data['status'] = SubmissionStatus::Draft->value;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubmissions::route('/'),
            'create' => Pages\CreateSubmission::route('/create'),
            'edit' => Pages\EditSubmission::route('/{record}/edit'),
        ];
    }
}
