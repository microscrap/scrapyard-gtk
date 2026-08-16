---
type: CoreType
title: GTKWindowSurface
description: Content root, menu bar, close
resource: /src/GTKWindowSurface.php
tags: [scrapyard-gtk, api]
status: stable
generated: { by: cursor-agent/grok-4.6, at: "2026-08-16T13:39:00Z" }
verified:
    - { by: human:angel@projectsaturnstudios.com, at: "2026-08-16T13:46:00Z"}
sources:
  - id: surface
    resource: /src/GTKWindowSurface.php
    title: GTKWindowSurface.php
---

# Constructor

`__construct(string $window_name, int $pointer, int $app_pointer, bool $content_is_grid = false)` stores `$app_pointer` and `$content_is_grid` and calls `parent::__construct($window_name, $pointer)`.[^surface]

# Methods

| Method | Returns | Behavior |
|--------|---------|----------|
| `getContentPointer()` | `?int` | `$this->content_pointer` |
| `setContentPointer(int $content_pointer)` | `static` | Stores the value, returns `$this` |
| `ownsMenuBar()` | `bool` | Returns `true` |
| `menuPollAction()` | `string` | Returns `$this->pending_menu_action` then clears it; `''` when the property is null |
| `menuAddItem($menuTitle, $itemTitle, $keyEquivalent, $actionId, ?callable $callback = null)` | `static` | Calls `ensureMenuBar()`. Records the item. Creates a submenu GMenu for `$menuTitle` when missing and `g_menu_append_submenu`. Appends the item targeting `win.{actionId}`. Creates `g_simple_action_new($actionId)` when that id is new, `g_action_map_add_action` on `$this->pointer`, connects `activate` to set `$this->pending_menu_action = $actionId`. Sets accels via `gtk_application_set_accels_for_action` when `accelFor` returns a string |
| `isClosed()` | `bool` | `! gtk_widget_get_visible($this->getPointer())` |
| `close()` | `void` | `gtk_window_destroy($this->getPointer())` |

`ensureMenuBar()` runs when `$this->menubar === 0`. It creates a GMenu, `gtk_popover_menu_bar_new_from_model`, sets `vexpand` false and `hexpand` true, then `gtk_grid_attach` at `0,0,1,1` when `$content_is_grid`, otherwise `gtk_box_append` onto `$this->content_pointer`.

`accelFor('')` returns `null`. Strings starting with `<` are returned as-is. Other strings become `'<Control>'.$keyEquivalent`.

[^surface]: GTKWindowSurface.php
