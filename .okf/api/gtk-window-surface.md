---
type: CoreType
title: GTKWindowSurface
description: Content root, menu bar, close
resource: /src/GTKWindowSurface.php
tags: [scrapyard-gtk, api]
status: stable
generated: { by: cursor-agent/grok-4.6, at: "2026-08-17T04:15:00Z" }
verified:
    - { by: human:angel@projectsaturnstudios.com, at: "2026-08-16T13:46:00Z"}
sources:
  - id: surface
    resource: /src/GTKWindowSurface.php
    title: GTKWindowSurface.php
---

# Constructor

`__construct(string $window_name, int $pointer, int $app_pointer, int $width, int $height, bool $content_is_grid = false)` stores `$app_pointer` and `$content_is_grid` and calls `parent::__construct($window_name, $pointer, $width, $height)`. Connects GTK4 `close-request` to mark `$destroyed` and return `false` (GTK4 has no `GtkWidget::destroy`).[^surface]

# Methods

| Method | Returns | Behavior |
|--------|---------|----------|
| `getContentPointer()` | `?int` | `$this->content_pointer` (GtkFixed, or GtkGrid when `$asGrid`) |
| `setContentPointer(int $content_pointer)` | `static` | Stores the value, returns `$this` |
| `getChromePointer()` | `?int` | Outer vertical GtkBox when not grid; `null` on grid windows |
| `setChromePointer(int $chrome_pointer)` | `static` | Stores the value, returns `$this` |
| `getChromeTopPointer()` | `?int` | Inner top GtkBox (menu slot) when not grid; `null` on grid windows |
| `setChromeTopPointer(int $chrome_top_pointer)` | `static` | Stores the value, returns `$this` |
| `ownsMenuBar()` | `bool` | Returns `true` |
| `menuPollAction()` | `string` | Returns `$this->pending_menu_action` then clears it; `''` when the property is null |
| `menuAddItem($menuTitle, $itemTitle, $keyEquivalent, $actionId, ?callable $callback = null)` | `static` | Calls `ensureMenuBar()`. Records the item. Creates a submenu GMenu for `$menuTitle` when missing and `g_menu_append_submenu`. Appends the item targeting `win.{actionId}`. Creates `g_simple_action_new($actionId)` when that id is new, `g_action_map_add_action` on `$this->pointer`, connects `activate` to set `$this->pending_menu_action = $actionId`. Sets accels via `gtk_application_set_accels_for_action` when `accelFor` returns a string |
| `isClosed()` | `bool` | `$destroyed` flag (set on window `close-request`, GTK4 has no `destroy` signal), else `! gtk_widget_get_visible($this->getPointer())` |
| `close()` | `void` | No-op when `$destroyed`; else marks destroyed then `gtk_window_destroy($this->getPointer())` |
| `present()` | `void` | No-op when `$destroyed`; else `gtk_window_present($this->getPointer())` |
| `nativeContentWidth()` | `int` | `gtk_widget_get_width($content_pointer)` when content is set and not destroyed; `0` when missing (used by inherited `pollResize`) |
| `nativeContentHeight()` | `int` | `gtk_widget_get_height($content_pointer)` when content is set and not destroyed; `0` when missing — measure **GtkFixed / GtkGrid content**, never the outer window widget (menubar chrome) |
| `setViewFrame($name, $x, $y, $h, $w)` | `static` | Throws on GtkGrid. Labels: `placeLabel` (natural width, move `x` by stored alignment). Other views: `size_request($w, $h)` then `gtk_fixed_move`. Unknown `$name` throws |
| `addView($name, ViewType, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `ViewType::LABEL` delegates to `addLabel`. `ViewType::BUTTON` delegates to `addButton`. Otherwise `gtk_widget_set_size_request($w, $h)` then `gtk_fixed_put` onto `content_pointer` at Tubes Y converted by `contentY`. Duplicate `$name` throws |
| `addLabel($name, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `gtk_label_new`, stores Tubes `alignment` for `placeLabel` (does **not** use `gtk_label_set_xalign`). Optional font CSS. Natural width, `gtk_fixed_put`, `placeLabel`, `map` re-place. Duplicate `$name` throws |
| `addButton($name, $x, $y, $h, $w, $addl_params = [])` | `static` | Throws on GtkGrid. `gtk_button_new_with_label`, size, `gtk_fixed_put` via `contentY`. Connects `clicked` to set `$this->pending_clicks[$name] = true`. Duplicate `$name` throws |
| `pollClick($name)` | `bool` | Returns `$this->pending_clicks[$name]` then clears it. Unknown `$name` throws |
| `setLabelText($name, $text)` | `static` | `gtk_label_set_text` then `placeLabel` if a stored frame exists. Unknown `$name` throws |
| `getEntryText($name)` | `string` | `gtk_entry_get_text` on the named entry handle. Unknown `$name` throws |
| `setEntryText($name, $text)` | `static` | `gtk_entry_set_text` on the named entry handle. Unknown `$name` throws |
| `isCheckboxChecked($name)` | `bool` | `gtk_check_button_get_active` on the named checkbox handle. Unknown `$name` throws |
| `setCheckboxChecked($name, $checked)` | `static` | `gtk_check_button_set_active` on the named checkbox handle. Unknown `$name` throws |
| `showAlert($message, $detail = '', $buttons = ['OK'])` | `static` | `gtk_alert_dialog_new`, optional detail/buttons, `gtk_alert_dialog_choose` with callback setting `$pending_alert_index`. Stores handle in `$alert_pointer`. Second call while open throws |
| `pollAlert()` | `?int` | Returns-and-clears `$pending_alert_index`; `null` when none this frame. Clears `$alert_pointer` after drain |

# Alerts

One alert per window (`$alert_pointer`). Uses `gtk_alert_dialog_choose` (not fire-and-forget `show`) so the response arrives during `pump()`.

`ensureMenuBar()` runs when `$this->menubar === 0`. It creates a GMenu, `gtk_popover_menu_bar_new_from_model`, sets `vexpand` false and `hexpand` true. Grid: `gtk_grid_attach` at `0,0,1,1` on `content_pointer`. Fixed: `gtk_box_append` the menubar onto `chrome_top_pointer`. The GtkFixed is never unparented.

Supported `ViewType` cases: `LABEL`, `BUTTON`, `ENTRY`, `CHECKBOX`, `SWITCH`, `PASSWORD`, `TEXT`, `IMAGE`, `SLIDER`, `PROGRESS`, `SPINNER`, `DROPDOWN`, `SCROLL`, `SPLIT`, `TABS`, `RADIO`. `POPOVER` throws (`GtkPopover` is not a fixed child).

Common `$addl_params`: `title` / `text`, `alignment` (`Tubes\Windows\Enums\TextAlignment` on LABEL → `placeLabel` x inside the given rect, not `gtk_label_set_xalign`), `font_size` / `font_weight` (`Tubes\Windows\Enums\FontWeight` on LABEL → CSS `font-size` pt / `font-weight` 100–900), `placeholder`, `active`, `path` / `file` (IMAGE), `min` / `max` / `value` / `step` (SLIDER, PROGRESS), `items` / `selected` (DROPDOWN), `vertical` (SPLIT default true; SLIDER when set), `group` (RADIO — name of another view in the same surface).

`accelFor('')` returns `null`. Strings starting with `<` are returned as-is. Other strings become `'<Control>'.$keyEquivalent`.

`contentY($y, $h)` returns `getCurrentHeight() - $y - $h`. Tubes Y is AppKit-style (origin bottom-left, Y up). GtkFixed is top-left, Y down. `placeLabel` / `addButton` / `addOtherView` and `setViewFrame` pass `contentY` into `gtk_fixed_put` / `gtk_fixed_move`. HelloLabel `CENTER` is widget `x`, not a stretched label (`alignedContentX`).

[^surface]: GTKWindowSurface.php
