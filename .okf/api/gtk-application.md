---
type: CoreType
title: GTKApplication
description: Init, windows, pump, terminate
resource: /src/GTKApplication.php
tags: [scrapyard-gtk, api]
status: stable
generated: { by: cursor-agent/grok-4.6, at: "2026-08-16T13:39:00Z" }
verified:
    - { by: human:angel@projectsaturnstudios.com, at: "2026-08-16T13:46:00Z"}
sources:
  - id: app
    resource: /src/GTKApplication.php
    title: GTKApplication.php
---

# Constructor

`__construct(string $application_id, int $application_flags)` calls `gtk_init_check()`, `gtk_application_new($this->application_id, $this->application_flags)` into `$this->app_pointer`, then `g_application_register($this->app_pointer)`, then `new Collection()` for `$this->windows`. Both constructor arguments are public readonly properties. `$app_pointer` is a public readonly `int`.[^app]

# Methods

| Method | Returns | Behavior |
|--------|---------|----------|
| `ownsMenuBar()` | `bool` | Returns `false` |
| `menuAddItem(...)` | `static` | Throws `OSApplicationException` with message `"menuAddItem via GTK happens in the Window."` |
| `createWindow($name, $width, $height, ?WindowSurface &$window = null, bool $asGrid = false)` | `static` | Throws `OSApplicationException::windowAlreadyCreated($name)` when the name is already stored. Otherwise `gtk_application_window_new`, `gtk_window_set_title`, `gtk_window_set_default_size`. Child is `gtk_grid_new()` when `$asGrid` is true, otherwise `gtk_box_new(Orientation::VERTICAL->value, 0)`. `gtk_window_set_child` attaches that widget. Constructs `GTKWindowSurface($name, $pointer, $this->app_pointer, $asGrid)`, `setContentPointer($content)`, stores in `$this->windows` |
| `pump()` | `void` | `while (g_main_context_iteration(g_main_context_default(), false)) {}` |
| `terminate()` | `void` | `gtk_application_quit($this->app_pointer)` |

[^app]: GTKApplication.php
