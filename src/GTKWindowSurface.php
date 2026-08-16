<?php

namespace Microscrap\ScrapyardIO\GTK;

use Tubes\Windows\WindowSurface;

class GTKWindowSurface extends WindowSurface
{
    protected ?int $content_pointer = null;

    protected array $menu_items = [];

    /** @var array<string, int> menuTitle => GMenu handle */
    protected array $menus = [];

    /** @var array<string, int> actionId => GSimpleAction handle */
    protected array $actions = [];

    protected int $menubar = 0;

    protected int $menubar_widget = 0;

    protected ?string $pending_menu_action = null;

    public function __construct(
        string $window_name,
        int $pointer,
        protected readonly int $app_pointer,
        protected readonly bool $content_is_grid = false,
    ) {
        parent::__construct($window_name, $pointer);
    }

    public function getContentPointer(): ?int
    {
        return $this->content_pointer;
    }

    public function setContentPointer(int $content_pointer): static
    {
        $this->content_pointer = $content_pointer;
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
        return !gtk_widget_get_visible($this->getPointer());
    }

    public function close(): void
    {
        gtk_window_destroy($this->getPointer());
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
        if ($content !== 0) {
            if ($this->content_is_grid) {
                gtk_grid_attach($content, $this->menubar_widget, 0, 0, 1, 1);
            } else {
                gtk_box_append($content, $this->menubar_widget);
            }
        }
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
}
