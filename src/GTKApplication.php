<?php

namespace Microscrap\ScrapyardIO\GTK;

use Fabricate\NutsAndBolts\Collection;
use Microscrap\Bindings\Gtk\Enums\Orientation;
use Tubes\Contracts\Windows\Exceptions\OSApplicationException;
use Tubes\Contracts\Windows\WindowSurface;
use Tubes\Windows\WindowableApplication;

class GTKApplication extends WindowableApplication
{
    public readonly int $app_pointer;

    protected int $menubar = 0;

    protected ?string $pending_menu_action = null;

    public function __construct(
        public readonly string $application_id,
        public readonly int $application_flags
    ) {
        gtk_init_check();
        $this->app_pointer = gtk_application_new($this->application_id, $this->application_flags);
        g_application_register($this->app_pointer);

        $this->windows = new Collection();

    }

    public function ownsMenuBar(): bool
    {
        return false;
    }

    /**
     * @throws OSApplicationException
     */
    public function menuAddItem(string $menuTitle, string $itemTitle, string $keyEquivalent, string $actionId, ?callable $callback = null): static
    {
        throw new OSApplicationException("menuAddItem via GTK happens in the Window.");
    }

    /**
     * @throws OSApplicationException
     */
    public function createWindow(string $name, int $width, int $height, ?WindowSurface &$window = null, bool $asGrid = false): static
    {
        if($this->windows->has($name))
        {
            throw OSApplicationException::windowAlreadyCreated($name);
        }

        $pointer = gtk_application_window_new($this->app_pointer);
        gtk_window_set_title($pointer, $name);
        gtk_window_set_default_size($pointer, $width, $height);

        if ($asGrid) {
            $content = gtk_grid_new();
            gtk_window_set_child($pointer, $content);
            $window = new GTKWindowSurface($name, $pointer, $this->app_pointer, $width, $height, true);
            $window = $window->setContentPointer($content);
        } else {
            $chrome = gtk_box_new(Orientation::VERTICAL->value, 0);
            $top = gtk_box_new(Orientation::VERTICAL->value, 0);
            gtk_widget_set_hexpand($top, true);
            gtk_widget_set_vexpand($top, false);
            $content = gtk_fixed_new();
            gtk_widget_set_hexpand($content, true);
            gtk_widget_set_vexpand($content, true);
            gtk_box_append($chrome, $top);
            gtk_box_append($chrome, $content);
            gtk_window_set_child($pointer, $chrome);
            $window = new GTKWindowSurface($name, $pointer, $this->app_pointer, $width, $height, false);
            $window = $window->setChromePointer($chrome)
                ->setChromeTopPointer($top)
                ->setContentPointer($content);
        }

        $this->windows->offsetSet($name, $window);

        return $this;
    }

    public function pump(): void
    {
        $hadEvents = false;
        while (g_main_context_iteration(g_main_context_default(), false)) {
            $hadEvents = true;
        }

        if (! $hadEvents) {
            g_main_context_iteration(g_main_context_default(), true);
        }
    }

    public function terminate(): void
    {
        gtk_application_quit($this->app_pointer);
    }
}
