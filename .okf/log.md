# Directory Update Log

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

* Default `createWindow` content is `GtkFixed` inside a vertical chrome `GtkBox`. Menu bar still packs on the chrome Box (remove Fixed, append bar, append Fixed). Grid path unchanged. Tubes not involved.
* `GTKWindowSurface::addView` implements Tubes `addView` via `gtk_fixed_put`.

## 2026-08-16

* **Initialization**: OKF v0.2 bundle for `microscrap/scrapyard-gtk` from `composer.json` and `src/GTKApplication.php`, `src/GTKWindowSurface.php`, `src/Providers/ScrapyardGTKServiceProvider.php`.
