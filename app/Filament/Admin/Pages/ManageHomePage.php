<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\PictureCards;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Admin → Home page: every section of the storefront home page, the
 * newsletter band and the About menu cards, saved as settings (home,
 * newsletter, about_menu). Texts are entered in English and Arabic.
 */
class ManageHomePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'home-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.home.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.home.title');
    }

    public function getTitle(): string
    {
        return __('admin.home.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public function mount(): void
    {
        $this->form->fill([
            'home' => Setting::get('home'),
            'newsletter' => Setting::get('newsletter'),
            'about_menu' => Setting::get('about_menu'),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::put('home', $state['home'] ?? []);
        Setting::put('newsletter', $state['newsletter'] ?? []);
        Setting::put('about_menu', $state['about_menu'] ?? []);

        Notification::make()->success()->title(__('admin.home.saved'))->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make('sections')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.home.tabs.hero'))->schema([
                    Repeater::make('home.hero')
                        ->label(__('admin.home.hero.slides'))
                        ->schema([
                            Select::make('type')->label(__('admin.home.hero.type'))->options(['image' => __('admin.home.hero.image'), 'video' => __('admin.home.hero.video')])->default('image')->live()->required(),
                            Grid::make(2)->schema([
                                PictureCards::image('image', 'home')->label(fn (Get $get): string => $get('type') === 'video' ? __('admin.home.hero.poster') : __('admin.picture_cards.image'))->required(),
                                self::video('video')->visible(fn (Get $get): bool => $get('type') === 'video')->required(fn (Get $get): bool => $get('type') === 'video'),
                            ]),
                            ...self::texts('kicker', 40),
                            ...self::texts('title', 60),
                            ...self::texts('button', 30),
                            PictureCards::link('link'),
                        ])
                        ->maxItems(5)->collapsible()->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? null),
                ]),

                Tab::make(__('admin.home.tabs.products'))->schema([
                    Section::make(__('admin.home.best.title'))->schema([
                        ...self::texts('home.best_sellers.kicker', 40),
                        Select::make('home.best_sellers.category_ids')
                            ->label(__('admin.home.best.categories'))
                            ->helperText(__('admin.home.best.help'))
                            ->multiple()->maxItems(4)
                            ->options(fn (): array => self::categories(byId: true)),
                    ]),
                    Section::make(__('admin.home.new.title'))->schema([
                        Select::make('home.new_in.category')->label(__('admin.home.new.category'))->options(fn (): array => self::categories())->default('new-arrivals'),
                        ...self::texts('home.new_in.kicker', 40),
                        ...self::texts('home.new_in.title', 60),
                        ...self::texts('home.new_in.button', 30),
                    ]),
                ]),

                Tab::make(__('admin.home.tabs.trio'))->schema([
                    Repeater::make('home.trio')
                        ->label(__('admin.home.trio.items'))
                        ->helperText(__('admin.home.trio.help'))
                        ->schema([
                            PictureCards::image('image', 'home')->required(),
                            Select::make('category')->label(__('admin.home.trio.category'))->options(fn (): array => self::categories())->required(),
                        ])
                        ->minItems(3)->maxItems(3)->defaultItems(3)->reorderable()->collapsible(),
                ]),

                Tab::make(__('admin.home.tabs.marquee'))->schema([
                    self::marquee('home.marquee_top', __('admin.home.marquee.top')),
                    self::marquee('home.marquee_bottom', __('admin.home.marquee.bottom')),
                ]),

                Tab::make(__('admin.home.tabs.video'))->schema([
                    Grid::make(2)->schema([
                        self::video('home.video.video'),
                        PictureCards::image('home.video.poster', 'home')->label(__('admin.home.hero.poster')),
                    ]),
                    ...self::texts('home.video.kicker', 40),
                    ...self::texts('home.video.title', 60),
                ]),

                Tab::make(__('admin.home.tabs.looks'))->schema([
                    Repeater::make('home.looks')
                        ->label(__('admin.home.looks.slides'))
                        ->helperText(__('admin.home.looks.help'))
                        ->schema([
                            PictureCards::image('image', 'home')->required()->live(),
                            Repeater::make('hotspots')
                                ->label(__('admin.home.looks.hotspots'))
                                ->schema([
                                    Select::make('product_id')->label(__('admin.home.looks.product'))->options(fn (): array => self::products())->searchable()->required(),
                                    Hidden::make('x')->default(50),
                                    Hidden::make('y')->default(50),
                                ])
                                ->minItems(1)->maxItems(4)->defaultItems(2)->live()
                                ->itemLabel(fn (array $state): ?string => isset($state['product_id']) ? (self::products()[$state['product_id']] ?? null) : null),
                            ViewField::make('picker')
                                ->view('filament.admin.hotspot-picker')
                                ->viewData(fn (Get $get): array => ['image' => self::previewUrl($get('image'))])
                                ->dehydrated(false),
                        ])
                        ->maxItems(4)->collapsible(),
                ]),

                Tab::make(__('admin.home.tabs.sale'))->schema([
                    Toggle::make('home.sale.enabled')->label(__('admin.home.sale.enabled')),
                    DateTimePicker::make('home.sale.ends_at')->label(__('admin.home.sale.ends_at'))->helperText(__('admin.home.sale.ends_help'))->seconds(false),
                    PictureCards::image('home.sale.image', 'home'),
                    ...self::texts('home.sale.kicker', 40),
                    ...self::texts('home.sale.title', 60),
                    ...self::texts('home.sale.text', 240, area: true),
                    ...self::texts('home.sale.button', 30),
                    PictureCards::link('home.sale.link'),
                ]),

                Tab::make(__('admin.home.tabs.compare'))->schema([
                    ...self::texts('home.compare.title', 60),
                    Grid::make(2)->schema([
                        Section::make(__('admin.home.compare.left'))->schema([PictureCards::image('home.compare.left.image', 'home'), ...self::texts('home.compare.left.label', 60), PictureCards::link('home.compare.left.link')]),
                        Section::make(__('admin.home.compare.right'))->schema([PictureCards::image('home.compare.right.image', 'home'), ...self::texts('home.compare.right.label', 60), PictureCards::link('home.compare.right.link')]),
                    ]),
                ]),

                Tab::make(__('admin.home.tabs.newsletter'))->schema([
                    PictureCards::image('newsletter.image', 'home'),
                    ...self::texts('newsletter.kicker', 40),
                    ...self::texts('newsletter.title', 60),
                    ...self::texts('newsletter.text', 240, area: true),
                ]),

                Tab::make(__('admin.home.tabs.about'))->schema([
                    PictureCards::repeater('about_menu.cards', 'menu'),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')->label(__('admin.home.save'))->submit('save')->keyBindings(['mod+s']),
                ])->sticky()->key('form-actions'),
            ]);
    }

    /** @return list<Grid> English + Arabic inputs for one text. */
    private static function texts(string $name, int $max, bool $area = false): array
    {
        $field = fn (string $lang) => ($area ? Textarea::make("{$name}.{$lang}")->rows(2) : TextInput::make("{$name}.{$lang}"))
            ->label(__('admin.home.fields.'.str($name)->afterLast('.')).' ('.strtoupper($lang).')')
            ->maxLength($max);

        return [Grid::make(2)->schema([$field('en'), $field('ar')])];
    }

    private static function video(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->label(__('admin.home.hero.video'))
            ->helperText(__('admin.home.video_help'))
            ->disk('public')->directory('home')->visibility('public')
            ->acceptedFileTypes(['video/mp4', 'video/webm'])
            ->maxSize(30720);
    }

    private static function marquee(string $name, string $label): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                ...self::texts('text', 60),
                Grid::make(2)->schema([
                    PictureCards::link('link'),
                    Select::make('product_id')->label(__('admin.home.marquee.thumb'))->options(fn (): array => self::products())->searchable(),
                ]),
            ])
            ->maxItems(6)->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['text']['en'] ?? null);
    }

    /** @return array<int|string, string> */
    private static function categories(bool $byId = false): array
    {
        return Category::query()->active()->orderBy('parent_id')->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Category $c): array => [($byId ? $c->getKey() : $c->slug) => $c->name])
            ->all();
    }

    /** @return array<int, string> */
    private static function products(): array
    {
        return once(fn (): array => Product::query()->orderBy('id')->get(['id', 'name'])->mapWithKeys(fn (Product $p): array => [$p->getKey() => $p->name])->all());
    }

    /** The slide photo for the hotspot picker: saved path or a fresh upload. */
    private static function previewUrl(mixed $state): ?string
    {
        $file = is_array($state) ? reset($state) : $state;

        if ($file instanceof TemporaryUploadedFile) {
            return $file->temporaryUrl();
        }

        return is_string($file) && $file !== '' ? Storage::disk('public')->url($file) : null;
    }
}
