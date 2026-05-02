<?php

namespace App\Filament\Resources;

use App\Enums\AssetType;
use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\SubmissionResource\Pages;
use App\Models\Submission;
use App\Services\ApproveSubmissionService;
use App\Support\FieldopsPhotoProcessor;
use App\Support\FieldopsPhotoUpload;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as RepeatableTableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubmissionResource extends Resource
{
    protected static ?string $model = Submission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'Penugasan Lapangan';

    protected static ?string $pluralModelLabel = 'Penugasan Lapangan';

    protected static ?string $navigationLabel = 'Penugasan Lapangan';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['project', 'technician', 'area', 'parentOdcAsset', 'ports', 'assigner', 'reviewer']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->is_active;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Callout::make('Review dilakukan dari halaman Lihat')
                ->info()
                ->description('Gunakan halaman Lihat untuk menyetujui, meminta koreksi, atau menolak penugasan. Halaman ini dipakai untuk memperbaiki data penugasan.')
                ->visible(fn (string $operation): bool => $operation === 'edit' && auth()->user()->isAdmin()),
            Callout::make('Data sudah masuk tahap review')
                ->warning()
                ->description('Perubahan pada data yang sudah diajukan atau disetujui dapat memengaruhi hasil review dan histori aset.')
                ->visible(fn (string $operation, ?Submission $record): bool => $operation === 'edit'
                    && auth()->user()->isAdmin()
                    && $record instanceof Submission
                    && in_array($record->status, [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted, SubmissionStatus::Approved], true)),
            Callout::make('Perlu dilengkapi sebelum diajukan')
                ->warning()
                ->description(fn (?Submission $record): string => implode(' ', static::missingSubmissionRequirements($record)))
                ->visible(fn (string $operation, ?Submission $record): bool => $operation !== 'create'
                    && ! auth()->user()->isAdmin()
                    && $record instanceof Submission
                    && in_array($record->status, [SubmissionStatus::Assigned, SubmissionStatus::CorrectionNeeded], true)
                    && ! static::isReadyForSubmission($record)),
            Callout::make('Perlu koreksi')
                ->warning()
                ->description(fn (?Submission $record): ?string => $record?->review_notes)
                ->visible(fn (?Submission $record): bool => ! auth()->user()->isAdmin()
                    && $record?->status === SubmissionStatus::CorrectionNeeded
                    && filled($record?->review_notes)),
            Section::make('Ringkasan Penugasan')->schema([
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('asset_type')->label('Jenis Aset')->badge(),
                TextEntry::make('project.name')->label('Proyek')->placeholder('-'),
                TextEntry::make('area.name')->label('Area')->placeholder('-'),
                TextEntry::make('work_date')->label('Tanggal Kerja')->date()->placeholder('-'),
                TextEntry::make('planned_coordinates')
                    ->label('Titik Tugas dari Admin')
                    ->state(fn (?Submission $record): string => static::formatCoordinates($record?->planned_latitude, $record?->planned_longitude)),
                TextEntry::make('parentOdcAsset.box_id')
                    ->label('ODC Induk')
                    ->placeholder('-')
                    ->visible(fn (?Submission $record): bool => $record?->asset_type === AssetType::Odp),
                TextEntry::make('assignment_notes')->label('Catatan Penugasan')->placeholder('-')->columnSpanFull(),
            ])
                ->visible(fn (string $operation): bool => $operation !== 'create' && ! auth()->user()->isAdmin())
                ->columns(2),
            Section::make('Target Pekerjaan')->schema([
                Forms\Components\Select::make('project_id')
                    ->label('Proyek')
                    ->relationship('project', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record)),
                Forms\Components\Select::make('area_id')
                    ->label('Area')
                    ->relationship('area', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record)),
                Forms\Components\Select::make('asset_type')
                    ->label('Jenis Aset')
                    ->options([AssetType::Odc->value => 'ODC', AssetType::Odp->value => 'ODP'])
                    ->required()
                    ->live()
                    ->disabled(fn (string $operation) => $operation !== 'create'),
                Forms\Components\DatePicker::make('work_date')
                    ->label('Tanggal Kerja')
                    ->required()
                    ->default(now())
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record)),
            ])
                ->visible(fn (string $operation): bool => $operation === 'create' || auth()->user()->isAdmin())
                ->columns(2),
            Section::make('PIC')->schema([
                Forms\Components\Select::make('technician_id')
                    ->label('Teknisi / PIC')
                    ->relationship('technician', 'name', fn ($query) => $query->where('role', UserRole::Technician->value))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record)),
            ])
                ->visible(fn (string $operation): bool => $operation === 'create' || auth()->user()->isAdmin())
                ->columns(2),
            Section::make('Lokasi Target')->schema([
                Forms\Components\TextInput::make('planned_latitude')
                    ->label('Lintang Titik Tugas')
                    ->helperText('Bisa diisi manual atau otomatis dari klik peta.')
                    ->numeric()
                    ->required()
                    ->rules(['numeric', 'between:-90,90']),
                Forms\Components\TextInput::make('planned_longitude')
                    ->label('Bujur Titik Tugas')
                    ->helperText('Bisa diisi manual atau otomatis dari klik peta.')
                    ->numeric()
                    ->required()
                    ->rules(['numeric', 'between:-180,180']),
                View::make('filament.forms.components.coordinate-map')
                    ->viewData(fn (string $operation): array => [
                        'title' => 'Pilih Titik Penugasan',
                        'description' => 'Tentukan titik lokasi yang harus dicek teknisi.',
                        'targetLatitudeField' => 'planned_latitude',
                        'targetLongitudeField' => 'planned_longitude',
                        'plannedLatitudeField' => null,
                        'plannedLongitudeField' => null,
                        'showUsePlanButton' => false,
                        'targetLabel' => 'Titik Tugas dari Admin',
                        'plannedLabel' => null,
                        'gpsButtonLabel' => 'Gunakan Lokasi Saya',
                        'manualStatusText' => 'Klik peta untuk menentukan titik tugas dari admin.',
                        'autoLocate' => false,
                        'showDistance' => false,
                        'isReadOnly' => $operation !== 'create' && ! auth()->user()->isAdmin(),
                    ])
                    ->columnSpanFull(),
            ])
                ->visible(fn (string $operation): bool => $operation === 'create' || auth()->user()->isAdmin())
                ->columns(2),
            Section::make('Instruksi')->schema([
                Forms\Components\Select::make('parent_odc_asset_id')
                    ->label('ODC Induk')
                    ->relationship('parentOdcAsset', 'box_id')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record))
                    ->visible(fn ($get) => $get('asset_type') === AssetType::Odp->value),
                Forms\Components\Textarea::make('assignment_notes')
                    ->label('Catatan Penugasan')
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record))
                    ->columnSpanFull(),
            ])
                ->visible(fn (string $operation): bool => $operation === 'create' || auth()->user()->isAdmin())
                ->columns(2),
            Section::make(fn (?Submission $record): string => auth()->user()->isAdmin() ? 'Data Lapangan' : 'Identitas Aset')->schema([
                Forms\Components\TextInput::make('box_id')
                    ->label(fn (?Submission $record) => $record?->asset_type === AssetType::Odp ? 'ODP Box ID' : 'ODC Box ID')
                    ->required(fn (string $operation) => $operation !== 'create')
                    ->maxLength(255),
                FieldopsPhotoUpload::configure(Forms\Components\FileUpload::make('photo_path'))
                    ->label('Foto Aset')
                    ->directory(fn (?Submission $record) => $record?->asset_type === AssetType::Odp ? 'submissions/odp' : 'submissions/odc')
                    ->visibility('public'),
            ])
                ->visible(fn (string $operation) => $operation !== 'create')
                ->columns(2),
            Section::make('Lokasi Lapangan')->schema([
                Forms\Components\TextInput::make('latitude')
                    ->label('Lintang Lokasi Laporan')
                    ->helperText(fn (?Submission $record): string => static::isTechnicianEditing($record)
                        ? 'Terisi otomatis dari lokasi GPS perangkat teknisi.'
                        : 'Isi dari GPS, klik peta, atau ketik manual jika perlu.')
                    ->numeric()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record))
                    ->dehydrated()
                    ->required(fn (string $operation) => $operation !== 'create')
                    ->rules(['numeric', 'between:-90,90']),
                Forms\Components\TextInput::make('longitude')
                    ->label('Bujur Lokasi Laporan')
                    ->helperText(fn (?Submission $record): string => static::isTechnicianEditing($record)
                        ? 'Terisi otomatis dari lokasi GPS perangkat teknisi.'
                        : 'Isi dari GPS, klik peta, atau ketik manual jika perlu.')
                    ->numeric()
                    ->disabled(fn (?Submission $record): bool => static::isTechnicianEditing($record))
                    ->dehydrated()
                    ->required(fn (string $operation) => $operation !== 'create')
                    ->rules(['numeric', 'between:-180,180']),
                View::make('filament.forms.components.coordinate-map')
                    ->viewData(fn (?Submission $record): array => [
                        'title' => 'Lokasi Aktual yang Dilaporkan Teknisi',
                        'description' => static::isTechnicianEditing($record)
                            ? 'Lokasi laporan diambil dari GPS perangkat teknisi. Lokasi ini akan dipakai sebagai lokasi resmi jika disetujui admin.'
                            : 'Isi lokasi aset sebenarnya di lapangan. Lokasi ini akan dipakai sebagai lokasi resmi jika disetujui admin.',
                        'targetLatitudeField' => 'latitude',
                        'targetLongitudeField' => 'longitude',
                        'plannedLatitudeField' => 'planned_latitude',
                        'plannedLongitudeField' => 'planned_longitude',
                        'showUsePlanButton' => auth()->user()->isAdmin(),
                        'targetLabel' => 'Lokasi Aktual yang Dilaporkan Teknisi',
                        'plannedLabel' => 'Titik Tugas dari Admin',
                        'gpsButtonLabel' => 'Ambil Lokasi GPS',
                        'manualStatusText' => static::isTechnicianEditing($record)
                            ? 'Ambil lokasi GPS perangkat untuk mengisi lokasi laporan.'
                            : 'Ambil lokasi GPS, klik peta, atau geser pin untuk mengisi lokasi laporan.',
                        'allowManualSelection' => auth()->user()->isAdmin(),
                        'autoLocate' => true,
                        'showDistance' => true,
                        'isReadOnly' => false,
                    ])
                    ->columnSpanFull(),
            ])
                ->visible(fn (string $operation) => $operation !== 'create')
                ->columns(2),
            Section::make('Detail ODP')->schema([
                Forms\Components\Select::make('core_color')
                    ->label('Warna Core ODP')
                    ->options(collect(OdpCoreColor::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required(fn (?Submission $record) => $record?->asset_type === AssetType::Odp)
                    ->visible(fn (?Submission $record) => $record?->asset_type === AssetType::Odp),
            ])
                ->visible(fn (string $operation, ?Submission $record) => $operation !== 'create' && $record?->asset_type === AssetType::Odp)
                ->columns(2),
            Section::make('Ketersediaan Port')->schema([
                Forms\Components\Repeater::make('ports')
                    ->label('Port')
                    ->relationship()
                    ->schema([
                        Forms\Components\Hidden::make('asset_type'),
                        Forms\Components\Select::make('port_number')
                            ->label('Nomor Port')
                            ->options(array_combine(range(1, 8), range(1, 8)))
                            ->required()
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')->label('Status')->options(PortStatus::simpleOptions())->required(),
                    ])
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Get $get): array => [
                        ...$data,
                        'asset_type' => $get('../../asset_type') ?? $data['asset_type'] ?? AssetType::Odc->value,
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Get $get): array => [
                        ...$data,
                        'asset_type' => $get('../../asset_type') ?? $data['asset_type'] ?? AssetType::Odc->value,
                    ])
                    ->columns(2)
                    ->defaultItems(8)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])->visible(fn (string $operation) => $operation !== 'create'),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan Teknisi')
                ->visible(fn (string $operation) => $operation !== 'create')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('review_notes')
                ->label('Catatan Review')
                ->disabled()
                ->visible(fn (?Submission $record) => filled($record?->review_notes))
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ringkasan')->schema([
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('asset_type')->label('Jenis Aset')->badge(),
                TextEntry::make('technician.name')->label('Teknisi / PIC')->placeholder('-'),
                TextEntry::make('project.name')->label('Proyek')->placeholder('-'),
                TextEntry::make('area.name')->label('Area')->placeholder('-'),
                TextEntry::make('work_date')->label('Tanggal Kerja')->date()->placeholder('-'),
                TextEntry::make('assigned_at')->label('Ditugaskan Pada')->dateTime()->placeholder('-'),
                TextEntry::make('submitted_at')->label('Diajukan Pada')->dateTime()->placeholder('-'),
            ])->columns(4),
            Section::make('Rencana Admin')->schema([
                TextEntry::make('planned_coordinates')
                    ->label('Titik Tugas dari Admin')
                    ->state(fn (?Submission $record): string => static::formatCoordinates($record?->planned_latitude, $record?->planned_longitude))
                    ->copyable(),
                TextEntry::make('assignment_notes')->label('Catatan Penugasan')->placeholder('-')->columnSpanFull(),
                TextEntry::make('assigner.name')->label('Dibuat Oleh')->placeholder('-'),
            ])->columns(2),
            Section::make('Hasil Lapangan')->schema([
                TextEntry::make('box_id')->label('Box ID')->placeholder('Belum diisi')->copyable(),
                TextEntry::make('actual_coordinates')
                    ->label('Lokasi Aktual yang Dilaporkan Teknisi')
                    ->state(fn (?Submission $record): string => static::formatCoordinates($record?->latitude, $record?->longitude))
                    ->copyable(),
                TextEntry::make('core_color')
                    ->label('Warna Core ODP')
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (?Submission $record): bool => $record?->asset_type === AssetType::Odp),
                TextEntry::make('parentOdcAsset.box_id')
                    ->label('ODC Induk')
                    ->placeholder('-')
                    ->visible(fn (?Submission $record): bool => $record?->asset_type === AssetType::Odp),
                ImageEntry::make('photo_path')
                    ->label('Foto Aset')
                    ->visibility('public')
                    ->imageHeight(220)
                    ->placeholder('Belum ada foto')
                    ->columnSpanFull(),
                TextEntry::make('notes')->label('Catatan Teknisi')->placeholder('-')->columnSpanFull(),
            ])
                ->description('Bandingkan titik tugas dari admin dengan lokasi aktual yang dilaporkan teknisi.')
                ->columns(2),
            Section::make('Ketersediaan Port')->schema([
                RepeatableEntry::make('ports')
                    ->label('Port')
                    ->placeholder('Belum ada data port')
                    ->table([
                        RepeatableTableColumn::make('Port')->width('96px'),
                        RepeatableTableColumn::make('Status'),
                    ])
                    ->schema([
                        TextEntry::make('port_number')->label('Port'),
                        TextEntry::make('status')->label('Status')->badge(),
                    ]),
            ]),
            Section::make('Review')->schema([
                TextEntry::make('review_notes')->label('Catatan Review')->placeholder('-')->columnSpanFull(),
                TextEntry::make('reviewer.name')->label('Direview Oleh')->placeholder('-'),
                TextEntry::make('reviewed_at')->label('Direview Pada')->dateTime()->placeholder('-'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->state(fn (?Submission $record): ?string => FieldopsPhotoProcessor::tableThumbnailPathFor($record?->photo_path))
                    ->visibility('public')
                    ->imageSize(48)
                    ->square(),
                Tables\Columns\TextColumn::make('project.name')->label('Proyek')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('technician.name')->label('Teknisi / PIC')->sortable()->searchable()->visible(fn () => auth()->user()->isAdmin()),
                Tables\Columns\TextColumn::make('area.name')->label('Area')->sortable(),
                Tables\Columns\TextColumn::make('asset_type')->label('Jenis Aset')->badge(),
                Tables\Columns\TextColumn::make('work_date')->label('Tanggal Kerja')->date()->sortable(),
                Tables\Columns\TextColumn::make('box_id')->label('Box ID')->searchable()->placeholder('Belum diisi'),
                Tables\Columns\TextColumn::make('core_color')->label('Warna Core')->badge()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parentOdcAsset.box_id')->label('ODC Induk')->placeholder('-'),
                Tables\Columns\TextColumn::make('planned_latitude')->label('Lintang Tugas')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('planned_longitude')->label('Bujur Tugas')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('latitude')->label('Lintang Laporan')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('longitude')->label('Bujur Laporan')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('assigned_at')->label('Ditugaskan Pada')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->label('Diajukan Pada')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')->label('Direview Pada')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')->label('Proyek')->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('technician')->label('Teknisi / PIC')->relationship('technician', 'name')->visible(fn () => auth()->user()->isAdmin()),
                Tables\Filters\SelectFilter::make('asset_type')->label('Jenis Aset')->options([AssetType::Odc->value => 'ODC', AssetType::Odp->value => 'ODP']),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(SubmissionStatus::class),
            ])
            ->defaultSort('work_date')
            ->striped()
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(fn (): string => auth()->user()->isAdmin() ? 'Belum ada penugasan' : 'Belum ada pekerjaan')
            ->emptyStateDescription(fn (): string => auth()->user()->isAdmin()
                ? 'Buat penugasan baru untuk teknisi dari tombol Penugasan Baru.'
                : 'Penugasan dari admin akan muncul di sini.')
            ->actions([
                Actions\ViewAction::make()->label('Lihat'),
                Actions\EditAction::make()
                    ->label(fn (Submission $record): string => auth()->user()->isAdmin()
                        ? 'Edit'
                        : ($record->status === SubmissionStatus::CorrectionNeeded ? 'Perbaiki' : 'Kerjakan'))
                    ->visible(fn (Submission $record) => static::canEdit($record)),
                static::submitAction(),
                static::approveAction(),
                static::requestCorrectionAction(),
                static::rejectAction(),
            ]);
    }

    public static function submitAction(): Actions\Action
    {
        return Actions\Action::make('submit')
            ->label(fn (Submission $record): string => $record->status === SubmissionStatus::CorrectionNeeded ? 'Kirim Ulang Laporan' : 'Kirim Laporan')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (Submission $record) => ! auth()->user()->isAdmin() && in_array($record->status, [SubmissionStatus::Assigned, SubmissionStatus::CorrectionNeeded], true))
            ->disabled(fn (Submission $record): bool => ! static::isReadyForSubmission($record))
            ->tooltip(fn (Submission $record): ?string => static::isReadyForSubmission($record) ? null : implode(' ', static::missingSubmissionRequirements($record)))
            ->requiresConfirmation()
            ->action(function (Submission $record): void {
                if (! static::isReadyForSubmission($record)) {
                    Notification::make()
                        ->danger()
                        ->title('Lengkapi data aset sebelum diajukan.')
                        ->body(implode(' ', static::missingSubmissionRequirements($record)))
                        ->send();

                    return;
                }

                $record->forceFill([
                    'status' => $record->status === SubmissionStatus::CorrectionNeeded ? SubmissionStatus::Resubmitted : SubmissionStatus::Submitted,
                    'submitted_at' => now(),
                ])->save();
            });
    }

    public static function approveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Submission $record) => auth()->user()->isAdmin() && static::canReview($record))
            ->form([Forms\Components\Textarea::make('review_notes')->label('Catatan Review')])
            ->action(function (Submission $record, array $data): void {
                app(ApproveSubmissionService::class)->approve($record, auth()->user(), $data['review_notes'] ?? null);
                Notification::make()->success()->title('Penugasan disetujui dan aset diperbarui.')->send();
            });
    }

    public static function requestCorrectionAction(): Actions\Action
    {
        return Actions\Action::make('requestCorrection')
            ->label('Minta Koreksi')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Submission $record) => auth()->user()->isAdmin() && static::canReview($record))
            ->form([Forms\Components\Textarea::make('review_notes')->label('Catatan Review')->required()])
            ->action(fn (Submission $record, array $data) => $record->forceFill([
                'status' => SubmissionStatus::CorrectionNeeded,
                'review_notes' => $data['review_notes'],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save());
    }

    public static function rejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Submission $record) => auth()->user()->isAdmin() && static::canReview($record))
            ->form([Forms\Components\Textarea::make('review_notes')->label('Catatan Review')->required()])
            ->action(fn (Submission $record, array $data) => $record->forceFill([
                'status' => SubmissionStatus::Rejected,
                'review_notes' => $data['review_notes'],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save());
    }

    public static function canReview(Submission $record): bool
    {
        return in_array($record->status, [SubmissionStatus::Submitted, SubmissionStatus::Resubmitted], true);
    }

    public static function isTechnicianEditing(?Submission $record): bool
    {
        return $record instanceof Submission && auth()->user()?->isAdmin() === false;
    }

    public static function canEdit(Model $record): bool
    {
        if (auth()->user()->isAdmin()) {
            return true;
        }

        return $record->technician_id === auth()->id()
            && in_array($record->status, [SubmissionStatus::Assigned, SubmissionStatus::CorrectionNeeded], true);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->isAdmin()
            || $record->technician_id === auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function isReadyForSubmission(Submission $record): bool
    {
        return static::missingSubmissionRequirements($record) === [];
    }

    /**
     * @return array<int, string>
     */
    public static function missingSubmissionRequirements(?Submission $record): array
    {
        if (! $record instanceof Submission) {
            return [];
        }

        $requirements = [];

        if (blank($record->box_id)) {
            $requirements[] = 'Box ID belum diisi.';
        }

        if (blank($record->latitude) || blank($record->longitude)) {
            $requirements[] = 'Koordinat aktual belum lengkap.';
        }

        if ($record->asset_type === AssetType::Odp && blank($record->core_color)) {
            $requirements[] = 'Warna core ODP belum dipilih.';
        }

        if ($record->ports()->count() < 1) {
            $requirements[] = 'Data port belum tersedia.';
        }

        return $requirements;
    }

    public static function formatCoordinates(mixed $latitude, mixed $longitude): string
    {
        if (blank($latitude) || blank($longitude)) {
            return '-';
        }

        return "{$latitude}, {$longitude}";
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_by'] = auth()->id();
        $data['assigned_at'] = now();
        $data['status'] = SubmissionStatus::Assigned->value;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubmissions::route('/'),
            'create' => Pages\CreateSubmission::route('/create'),
            'view' => Pages\ViewSubmission::route('/{record}'),
            'edit' => Pages\EditSubmission::route('/{record}/edit'),
        ];
    }
}
