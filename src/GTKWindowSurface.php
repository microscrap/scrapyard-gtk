<?php

namespace Microscrap\ScrapyardIO\GTK;

use Tubes\Windows\WindowSurface;
use Tubes\Windows\Enums\TextAlignment;
use Tubes\Windows\Enums\ViewType;
use Microscrap\Bindings\Gtk\Enums\PolicyType;
use Microscrap\Bindings\Gtk\Enums\Orientation;
use Tubes\Contracts\Windows\Exceptions\WindowableException;
use Microscrap\ScrapyardIO\GTK\Contracts\GTKWindowSurface as SurfaceContract;

class GTKWindowSurface extends WindowSurface implements SurfaceContract
{
    protected array $menu_items = [];

    /** @var array<string, int> menuTitle => GMenu handle */
    protected array $menus = [];

    /** @var array<string, int> actionId => GSimpleAction handle */
    protected array $actions = [];

    protected int $menubar = 0;

    protected int $menubar_widget = 0;

    protected ?int $chrome_pointer = null;

    protected ?int $chrome_top_pointer = null;

    protected ?string $pending_menu_action = null;

    /** @var array<string, bool> */
    protected array $pending_clicks = [];

    protected ?int $alert_pointer = null;

    protected ?int $pending_alert_index = null;

    protected bool $destroyed = false;

    /** @var array<string, string> extra CSS fragments preserved across setViewFrame */
    protected array $view_css = [];

    /** @var array<string, TextAlignment> */
    protected array $label_alignments = [];

    /** @var array<string, array{x: int, y: int, h: int, w: int}> */
    protected array $view_frames = [];

    public function __construct(
        string $window_name,
        int $pointer,
        protected readonly int $app_pointer,
        int $width,
        int $height,
        protected readonly bool $content_is_grid = false,

    ) {
        parent::__construct($window_name, $pointer, $width, $height);

        // GTK4 has no GtkWidget::destroy signal. close-request fires before
        // the window is disposed; returning false lets GTK destroy it.
        g_signal_connect($pointer, 'close-request', function (): bool {
            $this->markDestroyed();
            return false;
        });
    }

    protected function markDestroyed(): void
    {
        $this->destroyed = true;
    }

    public function getChromePointer(): ?int
    {
        return $this->chrome_pointer;
    }

    public function setChromePointer(int $chrome_pointer): static
    {
        $this->chrome_pointer = $chrome_pointer;
        return $this;
    }

    public function getChromeTopPointer(): ?int
    {
        return $this->chrome_top_pointer;
    }

    public function setChromeTopPointer(int $chrome_top_pointer): static
    {
        $this->chrome_top_pointer = $chrome_top_pointer;
        return $this;
    }

    public function ownsMenuBar(): bool
    {
        return true;
    }

    public function menuPollAction(): string
    {
        if (is_null($this->pending_menu_action)) {
            return '';
        }

        $action = $this->pending_menu_action;
        $this->pending_menu_action = null;

        return $action;
    }

    public function menuAddItem(string $menuTitle, string $itemTitle, string $keyEquivalent, string $actionId, ?callable $callback = null): static
    {
        $this->ensureMenuBar();

        $this->menu_items[] = [
            'menuTitle' => $menuTitle,
            'itemTitle' => $itemTitle,
            'keyEquivalent' => $keyEquivalent,
            'actionId' => $actionId,
        ];

        if (! isset($this->menus[$menuTitle])) {
            $this->menus[$menuTitle] = g_menu_new();
            g_menu_append_submenu($this->menubar, $menuTitle, $this->menus[$menuTitle]);
        }

        $detailedAction = 'win.'.$actionId;
        g_menu_append($this->menus[$menuTitle], $itemTitle, $detailedAction);

        if (! isset($this->actions[$actionId])) {
            $action = g_simple_action_new($actionId);
            g_action_map_add_action($this->pointer, $action);
            $this->actions[$actionId] = $action;

            g_signal_connect($action, 'activate', function () use($actionId): void {
                $this->pending_menu_action = $actionId;
            });
        }

        $accel = $this->accelFor($keyEquivalent);
        if (! is_null($accel)) {
            gtk_application_set_accels_for_action($this->app_pointer, $detailedAction, [$accel]);
        }

        return $this;
    }

    public function isClosed(): bool
    {
        if ($this->destroyed) {
            return true;
        }

        return ! gtk_widget_get_visible($this->getPointer());
    }

    public function close(): void
    {
        if ($this->destroyed) {
            return;
        }

        $this->markDestroyed();
        gtk_window_destroy($this->getPointer());
    }

    public function present(): void
    {
        if ($this->destroyed) {
            return;
        }

        gtk_window_present($this->getPointer());
    }

    protected function nativeContentWidth(): int
    {
        if ($this->destroyed) {
            return 0;
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            return 0;
        }

        return gtk_widget_get_width($content);
    }

    protected function nativeContentHeight(): int
    {
        if ($this->destroyed) {
            return 0;
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            return 0;
        }

        return gtk_widget_get_height($content);
    }

    protected function applyViewSize(int $handle, int $w, int $h, ?string $name = null): void
    {
        gtk_widget_set_size_request($handle, $w, $h);
        if (! is_null($name) && isset($this->view_css[$name])) {
            gtk_widget_apply_css($handle, $this->view_css[$name].';');
        }
    }

    protected function alignedContentX(int $boxX, int $boxW, int $natW, TextAlignment $alignment): int
    {
        if ($natW <= 0) {
            return $boxX;
        }

        return match ($alignment) {
            TextAlignment::LEFT => $boxX,
            TextAlignment::CENTER => $boxX + intdiv($boxW - $natW, 2),
            TextAlignment::RIGHT => $boxX + $boxW - $natW,
        };
    }

    protected function placeLabel(string $name, int $x, int $y, int $h, int $w): void
    {
        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            return;
        }

        $handle = $this->viewHandle($name);
        gtk_widget_set_size_request($handle, -1, $h);
        if (isset($this->view_css[$name])) {
            gtk_widget_apply_css($handle, $this->view_css[$name].';');
        }

        $natW = gtk_widget_get_width($handle);
        $align = $this->label_alignments[$name] ?? TextAlignment::LEFT;
        $putX = $this->alignedContentX($x, $w, $natW, $align);
        gtk_fixed_move($content, $handle, $putX, $this->contentY($y, $h));
        $this->view_frames[$name] = ['x' => $x, 'y' => $y, 'h' => $h, 'w' => $w];
    }

    /**
     * @throws WindowableException
     */
    public function setViewFrame(string $name, int $x, int $y, int $h, int $w): static
    {
        if ($this->destroyed) {
            return $this;
        }

        if ($this->content_is_grid) {
            throw new WindowableException('setViewFrame requires GtkFixed content, not GtkGrid.');
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            throw new WindowableException("Window {$this->window_name} has no content view.");
        }

        $handle = $this->viewHandle($name);
        if (isset($this->label_alignments[$name])) {
            $this->placeLabel($name, $x, $y, $h, $w);
            return $this;
        }

        $this->applyViewSize($handle, $w, $h, $name);
        gtk_fixed_move($content, $handle, $x, $this->contentY($y, $h));

        return $this;
    }

    /**
     * @throws WindowableException
     */
    public function addOtherView(
        string $name,
        ViewType $view_component_enum,
        int $x,
        int $y,
        int $h,
        int $w,
        array $addl_params = []
    ): static {
        if ($this->content_is_grid) {
            throw new WindowableException("addView requires GtkFixed content, not GtkGrid.");
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            throw new WindowableException("Window {$this->window_name} has no content view.");
        }

        $title = (string) ($addl_params['title'] ?? $addl_params['text'] ?? $name);
        $items = $addl_params['items'] ?? [];
        if (! is_array($items) || $items === []) {
            $items = [$title];
        }
        $min = (float) ($addl_params['min'] ?? 0);
        $max = (float) ($addl_params['max'] ?? 100);
        $step = (float) ($addl_params['step'] ?? 1);
        $path = (string) ($addl_params['path'] ?? $addl_params['file'] ?? '');
        $splitVertical = (bool) ($addl_params['vertical'] ?? true);
        $sliderOrientation = ! empty($addl_params['vertical'])
            ? Orientation::VERTICAL->value
            : Orientation::HORIZONTAL->value;
        $splitOrientation = $splitVertical
            ? Orientation::VERTICAL->value
            : Orientation::HORIZONTAL->value;

        $handle = match ($view_component_enum) {
            ViewType::ENTRY => gtk_entry_new(),
            ViewType::CHECKBOX => gtk_check_button_new_with_label($title),
            ViewType::SWITCH => gtk_switch_new(),
            ViewType::PASSWORD => gtk_password_entry_new(),
            ViewType::TEXT => gtk_text_view_new(),
            ViewType::IMAGE => gtk_picture_new(),
            ViewType::SLIDER => gtk_scale_new_with_range($sliderOrientation, $min, $max, $step),
            ViewType::PROGRESS => gtk_progress_bar_new(),
            //ViewType::SPINNER => gtk_spinner_new(), // @todo - appkit requires extra steps, so keep unavailable until
            ViewType::DROPDOWN => gtk_drop_down_new_from_strings(array_map(static fn ($item): string => (string) $item, $items)),
            ViewType::SCROLL => gtk_scrolled_window_new(),
            ViewType::SPLIT => gtk_paned_new($splitOrientation),
            ViewType::TABS => gtk_notebook_new(),
            //ViewType::RADIO => gtk_check_button_new_with_label($title), // @todo - must define a group of them to not be a checkbox, so keep unavailable until
            default => throw new WindowableException("ViewType {$view_component_enum->name} is not available on GTK addView."),
        };

        if ($view_component_enum === ViewType::ENTRY && isset($addl_params['text'])) {
            gtk_entry_set_text($handle, (string) $addl_params['text']);
        }

        if ($view_component_enum === ViewType::ENTRY && isset($addl_params['placeholder'])) {
            gtk_entry_set_placeholder_text($handle, (string) $addl_params['placeholder']);
        }

        if ($view_component_enum === ViewType::PASSWORD && isset($addl_params['text'])) {
            gtk_password_entry_set_text($handle, (string) $addl_params['text']);
        }

        if ($view_component_enum === ViewType::TEXT && isset($addl_params['text'])) {
            gtk_text_view_set_text($handle, (string) $addl_params['text']);
        }

        if ($view_component_enum === ViewType::IMAGE && $path !== '') {
            gtk_picture_set_filename($handle, $path);
        }

        if ($view_component_enum === ViewType::SLIDER && isset($addl_params['value'])) {
            gtk_scale_set_value($handle, (float) $addl_params['value']);
        }

        if ($view_component_enum === ViewType::PROGRESS && isset($addl_params['value'])) {
            $range = $max - $min;
            $fraction = $range > 0 ? ((float) $addl_params['value'] - $min) / $range : 0.0;
            gtk_progress_bar_set_fraction($handle, $fraction);
        }

        /*
        if ($view_component_enum === ViewType::SPINNER) {
            gtk_spinner_start($handle);
        }*/

        if ($view_component_enum === ViewType::DROPDOWN && isset($addl_params['selected'])) {
            gtk_drop_down_set_selected($handle, (int) $addl_params['selected']);
        }

        if ($view_component_enum === ViewType::SCROLL) {
            gtk_scrolled_window_set_min_content_width($handle, $w);
            gtk_scrolled_window_set_min_content_height($handle, $h);
            gtk_scrolled_window_set_policy($handle, PolicyType::AUTOMATIC->value, PolicyType::AUTOMATIC->value);
        }

        /*
        if ($view_component_enum === ViewType::RADIO && isset($addl_params['group'])) {
            $groupName = (string) $addl_params['group'];
            if (isset($this->views[$groupName])) {
                gtk_check_button_set_group($handle, $this->views[$groupName]);
            }
        }*/

        if (isset($addl_params['active']) && in_array($view_component_enum, [ViewType::CHECKBOX, ViewType::RADIO], true)) {
            gtk_check_button_set_active($handle, (bool) $addl_params['active']);
        }

        if (isset($addl_params['active']) && $view_component_enum === ViewType::SWITCH) {
            gtk_switch_set_active($handle, (bool) $addl_params['active']);
        }

        $this->applyViewSize($handle, $w, $h, $name);
        gtk_fixed_put($content, $handle, $x, $this->contentY($y, $h));
        $this->rememberView($name, $handle);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $addl_params
     */
    public function addView(
        string $name,
        ViewType $view_component_enum,
        int $x,
        int $y,
        int $h,
        int $w,
        array $addl_params = []
    ): static {

        return match ($view_component_enum) {
            ViewType::LABEL => $this->addLabel($name, $x, $y, $h, $w, $addl_params),
            ViewType::BUTTON => $this->addButton($name, $x, $y, $h, $w, $addl_params),
            default => $this->addOtherView($name, $view_component_enum, $x, $y, $h, $w, $addl_params),
        };
    }

    /**
     * @param array<string, mixed> $addl_params
     * @throws WindowableException
     */
    public function addButton(
        string $name,
        int $x,
        int $y,
        int $h,
        int $w,
        array $addl_params = []
    ): static {
        if ($this->content_is_grid) {
            throw new WindowableException("addButton requires GtkFixed content, not GtkGrid.");
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            throw new WindowableException("Window {$this->window_name} has no content view.");
        }

        $title = (string) ($addl_params['title'] ?? $addl_params['text'] ?? $name);
        $handle = gtk_button_new_with_label($title);
        $this->applyViewSize($handle, $w, $h, $name);
        gtk_fixed_put($content, $handle, $x, $this->contentY($y, $h));
        $this->rememberView($name, $handle);

        g_signal_connect($handle, 'clicked', function () use ($name): void {
            $this->pending_clicks[$name] = true;
        });

        return $this;
    }

    /**
     * @throws WindowableException
     */
    public function pollClick(string $name): bool
    {
        $this->viewHandle($name);
        $clicked = $this->pending_clicks[$name] ?? false;
        $this->pending_clicks[$name] = false;

        return $clicked;
    }

    /**
     * @throws WindowableException
     */
    public function setLabelText(string $name, string $text): static
    {
        gtk_label_set_text($this->viewHandle($name), $text);
        if (isset($this->view_frames[$name], $this->label_alignments[$name])) {
            $frame = $this->view_frames[$name];
            $this->placeLabel($name, $frame['x'], $frame['y'], $frame['h'], $frame['w']);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $addl_params
     * @throws WindowableException
     */
    public function addLabel(
        string $name,
        int $x,
        int $y,
        int $h,
        int $w,
        array $addl_params = []
    ): static {
        if ($this->content_is_grid) {
            throw new WindowableException("addLabel requires GtkFixed content, not GtkGrid.");
        }

        $content = $this->getContentPointer();
        if (is_null($content) || $content === 0) {
            throw new WindowableException("Window {$this->window_name} has no content view.");
        }

        $title = (string) ($addl_params['title'] ?? $addl_params['text'] ?? $name);
        $handle = gtk_label_new($title);
        $alignment = $this->textAlignmentFrom($addl_params) ?? TextAlignment::LEFT;
        $this->label_alignments[$name] = $alignment;

        $fontSize = $this->fontSizeFrom($addl_params);
        $fontWeight = $this->fontWeightFrom($addl_params);
        if (! is_null($fontSize) || ! is_null($fontWeight)) {
            $cssParts = [];
            if (! is_null($fontSize)) {
                $cssParts[] = "font-size: {$fontSize}pt";
            }
            if (! is_null($fontWeight)) {
                $cssWeight = ($fontWeight->value + 1) * 100;
                $cssParts[] = "font-weight: {$cssWeight}";
            }
            $this->view_css[$name] = implode('; ', $cssParts);
            gtk_widget_apply_css($handle, $this->view_css[$name].';');
        }

        gtk_widget_set_size_request($handle, -1, $h);
        $this->rememberView($name, $handle);
        $this->view_frames[$name] = ['x' => $x, 'y' => $y, 'h' => $h, 'w' => $w];
        gtk_fixed_put($content, $handle, $x, $this->contentY($y, $h));
        $this->placeLabel($name, $x, $y, $h, $w);
        g_signal_connect($handle, 'map', function () use ($name): void {
            if (! isset($this->view_frames[$name])) {
                return;
            }
            $frame = $this->view_frames[$name];
            $this->placeLabel($name, $frame['x'], $frame['y'], $frame['h'], $frame['w']);
        });

        return $this;
    }

    protected function ensureMenuBar(): void
    {
        if ($this->menubar !== 0) {
            return;
        }

        $this->menubar = g_menu_new();
        $this->menubar_widget = gtk_popover_menu_bar_new_from_model($this->menubar);
        gtk_widget_set_vexpand($this->menubar_widget, false);
        gtk_widget_set_hexpand($this->menubar_widget, true);

        $content = $this->content_pointer ?? 0;
        if ($content === 0) {
            return;
        }

        if ($this->content_is_grid) {
            gtk_grid_attach($content, $this->menubar_widget, 0, 0, 1, 1);
            return;
        }

        $top = $this->chrome_top_pointer ?? 0;
        if ($top === 0) {
            return;
        }

        gtk_box_append($top, $this->menubar_widget);
    }

    /**
     * Tubes Y is AppKit-style (origin bottom-left, Y up). GtkFixed is top-left, Y down.
     */
    protected function contentY(int $y, int $h): int
    {
        return $this->getCurrentHeight() - $y - $h;
    }

    protected function accelFor(string $keyEquivalent): ?string
    {
        if ($keyEquivalent === '') {
            return null;
        }

        if (str_starts_with($keyEquivalent, '<')) {
            return $keyEquivalent;
        }

        return '<Control>'.$keyEquivalent;
    }

    /**
     * @throws WindowableException
     */
    public function getEntryText(string $name): string
    {
        return gtk_entry_get_text($this->viewHandle($name));
    }

    /**
     * @throws WindowableException
     */
    public function setEntryText(string $name, string $text): static
    {
        gtk_entry_set_text($this->viewHandle($name), $text);

        return $this;
    }

    /**
     * @throws WindowableException
     */
    public function isCheckboxChecked(string $name): bool
    {
        return gtk_check_button_get_active($this->viewHandle($name));
    }

    /**
     * @throws WindowableException
     */
    public function setCheckboxChecked(string $name, bool $checked): static
    {
        gtk_check_button_set_active($this->viewHandle($name), $checked);

        return $this;
    }

    /**
     * @param  array<int, string>  $buttons
     * @throws WindowableException
     */
    public function showAlert(string $message, string $detail = '', array $buttons = ['OK']): static
    {
        if (! is_null($this->alert_pointer)) {
            throw new WindowableException("An alert is already open on window {$this->window_name}.");
        }

        $alert = gtk_alert_dialog_new($message);
        if ($detail !== '') {
            gtk_alert_dialog_set_detail($alert, $detail);
        }
        gtk_alert_dialog_set_buttons($alert, $buttons);
        gtk_alert_dialog_choose($alert, $this->getPointer(), function (int $index): void {
            $this->pending_alert_index = $index;
        });
        $this->alert_pointer = $alert;

        return $this;
    }

    public function pollAlert(): ?int
    {
        if (is_null($this->pending_alert_index)) {
            return null;
        }

        $index = $this->pending_alert_index;
        $this->pending_alert_index = null;

        if (! is_null($this->alert_pointer)) {
            g_object_unref($this->alert_pointer);
            $this->alert_pointer = null;
        }

        return $index;
    }

    public function toggleAboutMenuHook(): void
    {
        $this->menuAddItem('Help', 'About', 'a', 'about');
    }

    public function ownsAboutMenu(): bool
    {
        return true;
    }
}
