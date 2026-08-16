---
type: CoreType
title: GTKWindowSurface
description: Content root, menu bar, close
resource: /src/GTKWindowSurface.php
tags: [scrapyard-gtk, api]
status: stable
generated: { by: cursor-agent/grok-4.6, at: "2026-08-16T21:20:00Z" }
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
| `getContentPointer()` | `?int` | `$this->content_pointer` (GtkFixed, or GtkGrid when `$asGrid`) |
| `setContentPointer(int $content_pointer)` | `static` | Stores the value, returns `$this` |
| `getChromePointer()` | `?int` | Outer vertical GtkBox when not grid; `null` on grid windows |
| `setChromePointer(int $chrome_pointer)` | `static` | Stores the value, returns `$this` |
| `ownsMenuBar()` | `bool` | Returns `true` |
| `menuPollAction()` | `string` | Returns `$this->pending_menu_action` then clears it; `''` when the property is null |
| `menuAddItem($menuTitle, $itemTitle, $keyEquivalent, $actionId, ?callable $callback = null)` | `static` | Calls `ensureMenuBar()`. Records the item. Creates a submenu GMenu for `$menuTitle` when missing and `g_menu_append_submenu`. Appends the item targeting `win.{actionId}`. Creates `g_simple_action_new($actionId)` when that id is new, `g_action_map_add_action` on `$this->pointer`, connects `activate` to set `$this->pending_menu_action = $actionId`. Sets accels via `gtk_application_set_accels_for_action` when `accelFor` returns a string |
| `isClosed()` | `bool` | `! gtk_widget_get_visible($this->getPointer())` |
| `close()` | `void` | `gtk_window_destroy($this->getPointer())` |
| `addView($name, ViewType, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `ViewType::LABEL` delegates to `addLabel`. `ViewType::BUTTON` delegates to `addButton`. Otherwise `gtk_widget_set_size_request($w, $h)` then `gtk_fixed_put` onto `content_pointer` at Tubes Y converted by `contentY`. Duplicate `$name` throws |
| `addLabel($name, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `gtk_label_new`, optional `gtk_label_set_xalign` from `$addl_params['alignment']`, `gtk_widget_set_size_request`, `gtk_fixed_put` via `contentY`. Duplicate `$name` throws |
| `addButton($name, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `gtk_button_new_with_label`, size, `gtk_fixed_put` via `contentY`. Connects `clicked` to set `$this->pending_clicks[$name] = true`. Duplicate `$name` throws |
| `pollClick($name)` | `bool` | Returns `$this->pending_clicks[$name]` then clears it. Unknown `$name` throws |
| `setLabelText($name, $text)` | `static` | `gtk_label_set_text` on the named view handle. Unknown `$name` throws |

`ensureMenuBar()` runs when `$this->menubar === 0`. It creates a GMenu, `gtk_popover_menu_bar_new_from_model`, sets `vexpand` false and `hexpand` true. Grid: `gtk_grid_attach` at `0,0,1,1` on `content_pointer`. Fixed: `gtk_box_remove` the Fixed from `chrome_pointer`, `gtk_box_append` the menubar, then append the Fixed again (menubar on top; no `gtk_box_prepend` bind).

Supported `ViewType` cases: `LABEL`, `BUTTON`, `ENTRY`, `CHECKBOX`, `SWITCH`, `PASSWORD`, `TEXT`, `IMAGE`, `SLIDER`, `PROGRESS`, `SPINNER`, `DROPDOWN`, `SCROLL`, `SPLIT`, `TABS`, `RADIO`. `POPOVER` throws (`GtkPopover` is not a fixed child).

Common `$addl_params`: `title` / `text`, `alignment` (`Tubes\Windows\Enums\TextAlignment` on LABEL → `gtk_label_set_xalign`), `placeholder`, `active`, `path` / `file` (IMAGE), `min` / `max` / `value` / `step` (SLIDER, PROGRESS), `items` / `selected` (DROPDOWN), `vertical` (SPLIT default true; SLIDER when set), `group` (RADIO — name of another view in the same surface).

`accelFor('')` returns `null`. Strings starting with `<` are returned as-is. Other strings become `'<Control>'.$keyEquivalent`.

`contentY($y, $h)` returns `getCurrentHeight() - $y - $h`. Tubes Y is AppKit-style (origin bottom-left, Y up). GtkFixed is top-left, Y down. `addLabel` / `addButton` / `addOtherView` pass `contentY` into `gtk_fixed_put`. Centered HelloLabel math is unchanged (`600 - 288 - 24 = 288`).

[^surface]: GTKWindowSurface.php
