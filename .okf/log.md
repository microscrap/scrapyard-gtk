# Directory Update Log

## 2026-08-17 (closeWindow inherited)

* `GTKApplication` inherits Tubes `closeWindow`. No GTK-only method. After the user closes a window, `close-request` already destroyed the widget; `closeWindow` drops the name from `$this->windows` so `createWindow` can conjure it again.

## 2026-08-17 (label align by x, not size_request)

* Split the fight: GTK `xalign` needs a full-width box; `size_request($w)` makes that box the window minimum so X cannot shrink. Labels now keep natural width (`size_request(-1, $h)`), store Tubes alignment, and `placeLabel` moves `x` inside the given rect (`CENTER` = `boxX + intdiv(boxW - natW, 2)`). `map` re-places once allocated. Buttons/other views still `size_request($w, $h)`. CSS is font-only on labels — no `width` / `min-width` on the widget.

## 2026-08-17 (GTK CSS has no width)

* GTK CSS does not define `width` / `height` (`Theme parser error: No property named "width"`). `applyViewSize` now uses `gtk_widget_set_size_request($w, $h)` for the visual frame and only CSS `min-width: 0px` (plus stored font CSS). Chrome/Fixed already have `min-width: 0px` so the window can still shrink. Full-width labels get `xalign` room again.

## 2026-08-17 (GTK4 close-request + CSS units)

* GTK4 removed `GtkWidget::destroy`. Connecting `destroy` never ran, so `$destroyed` stayed false and `pollResize` / `isClosed` / `close` hit dead pointers (`GTK_IS_WIDGET` CRITICAL). Now `close-request` marks destroyed and returns `false` (same pattern as `proof_vulkan_x11.php`). `close()` marks destroyed before `gtk_window_destroy`.
* GTK CSS requires units: `min-width: 0` is invalid and dropped. Use `min-width: 0px` on children, chrome, and GtkFixed so the window can shrink below the last child width.

## 2026-08-17 (GTK resize shrink + destroy lifecycle)

* `gtk_widget_set_size_request($w, $h)` on fixed children set **minimum** width and blocked horizontal window shrink after stretch. `applyViewSize` now uses height-only size request (`-1`, `$h`) plus CSS `min-width: 0; width: {$w}px;`. `addLabel` / `addButton` / `addOtherView` / `setViewFrame` use it; label font CSS stored in `$view_css[$name]` for resize.
* Window `destroy` signal sets `$destroyed`; `isClosed`, `close`, `present`, `pollResize` natives, and `setViewFrame` guard destroyed pointers (fixes CRITICAL warnings and zombie runner after closing window / ^C).

## 2026-08-17 (pollResize / setViewFrame)

* `nativeContentWidth` / `nativeContentHeight` read `gtk_widget_get_width` / `gtk_widget_get_height` on `$content_pointer` (GtkFixed), not the window widget; return `0` when content unset so Tubes `pollResize` ignores pre-allocate. `setViewFrame` uses `gtk_widget_set_size_request` + `gtk_fixed_move` with `contentY`. Throws on GtkGrid.

## 2026-08-17 (menubar top slot)

* Chrome is now `GtkBox` → empty top `GtkBox` (`chrome_top_pointer`, vexpand false) + `GtkFixed` (`content_pointer`). `ensureMenuBar` appends `GtkPopoverMenuBar` into the top slot. GtkFixed is never `gtk_box_remove`d. Empty top slot stays 0 height when no menu is added.

## 2026-08-17 (pump blocking / alert unref)

* `GTKApplication::pump` blocks only when the non-blocking drain processed zero events (fixes Hello Button lag: click handled in prep must not wait for a second event before exec).
* `GTKWindowSurface::pollAlert` calls `g_object_unref` on `$alert_pointer` when the async choose completes.

## 2026-08-17 (label font_size / font_weight)

* `GTKWindowSurface::addLabel` reads Tubes `fontSizeFrom` / `fontWeightFrom`. When either is set, builds per-widget CSS (`font-size: {n}pt`, `font-weight: {(value+1)*100}`) and `gtk_widget_apply_css` on the label handle.

## 2026-08-17 (HelloForm04 entry/checkbox/alert)

* `GTKWindowSurface` implements Tubes entry/checkbox get-set (`gtk_entry_*`, `gtk_check_button_*`) and alert choose API (`gtk_alert_dialog_choose` → `$pending_alert_index`, drained by `pollAlert`). One alert per window.

## 2026-08-16 (Y origin)

* `contentY($y, $h)` = `currentHeight - y - h`. Applied on every `gtk_fixed_put` so naive Tubes/AppKit Y (bottom-left, Y up) lands the same on GtkFixed.

## 2026-08-16 (addButton / pollClick / setLabelText)

* `GTKWindowSurface::addButton` creates the GtkButton, sizes, `gtk_fixed_put`, and `g_signal_connect('clicked')` into `$pending_clicks[$name]`. `addView(ViewType::BUTTON, …)` returns `$this->addButton(…)`.
* `pollClick($name)` returns-and-clears that flag. `setLabelText($name, $text)` is `gtk_label_set_text`.

## 2026-08-16 (addLabel)

* `GTKWindowSurface::addLabel` creates the GtkLabel, applies `xalign`, sizes, and `gtk_fixed_put`. `addView(ViewType::LABEL, …)` returns `$this->addLabel(…)`.

## 2026-08-16 (LABEL alignment)

* `GTKWindowSurface::addView` applies `$addl_params['alignment']` on `ViewType::LABEL` via `gtk_label_set_xalign` (0.0 / 0.5 / 1.0).

## 2026-08-16 (ViewType addView parity)

* `GTKWindowSurface::addView` now handles all Tubes `ViewType` cases except `POPOVER` (throws). Maps to gtk password entry, text view, picture, scale, progress bar, spinner, drop down, scrolled window, paned, notebook, and grouped radio check buttons.

## 2026-08-16 (GtkFixed content)

* Default `createWindow` content is `GtkFixed` inside a vertical chrome `GtkBox` that already has an empty top slot. Menu bar appends into that slot. Grid path unchanged. Tubes not involved.
* `GTKWindowSurface::addView` implements Tubes `addView` via `gtk_fixed_put`.

## 2026-08-16

* **Initialization**: OKF v0.2 bundle for `microscrap/scrapyard-gtk` from `composer.json` and `src/GTKApplication.php`, `src/GTKWindowSurface.php`, `src/Providers/ScrapyardGTKServiceProvider.php`.
