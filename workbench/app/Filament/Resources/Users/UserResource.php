<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Users;

use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Users\Pages\CreateUser;
use Workbench\App\Filament\Resources\Users\Pages\EditUser;
use Workbench\App\Filament\Resources\Users\Pages\ListUsers;
use Workbench\App\Models\User;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
            Select::make('is_active')->options([1 => 'Yes', 0 => 'No'])->searchable(),
            Toggle::make('flag')->dehydrated(false),
            DatePicker::make('born')->dehydrated(false),
            RichEditor::make('bio')->dehydrated(false),
            MarkdownEditor::make('notes')->dehydrated(false),
            FileUpload::make('avatar')->dehydrated(false),
            ColorPicker::make('color')->dehydrated(false),
            TagsInput::make('tags')->dehydrated(false),
            KeyValue::make('meta')->dehydrated(false),
            CodeEditor::make('code')->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
