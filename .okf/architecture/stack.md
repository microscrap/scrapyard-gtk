---
type: Architecture
title: Application and surface
description: Classes shipped in src/
resource: /src/GTKApplication.php
tags: [scrapyard-gtk, architecture]
status: stable
generated: { by: cursor-agent/grok-4.6, at: "2026-08-16T16:57:00Z" }
verified:
    - { by: human:angel@projectsaturnstudios.com, at: "2026-08-16T13:46:00Z"}
sources:
  - id: app
    resource: /src/GTKApplication.php
    title: GTKApplication
  - id: surface
    resource: /src/GTKWindowSurface.php
    title: GTKWindowSurface
  - id: provider
    resource: /src/Providers/ScrapyardGTKServiceProvider.php
    title: ScrapyardGTKServiceProvider
---

# Source map

| Path | Role |
|------|------|
| `src/GTKApplication.php` | `GTKApplication` extends `WindowableApplication`. Constructor calls `gtk_init_check()`, `gtk_application_new`, `g_application_register`, and creates `$this->windows`.[^app] |
| `src/GTKWindowSurface.php` | `GTKWindowSurface` extends `WindowSurface`. Holds content pointer, GMenu state, and pending menu action.[^surface] |
| `src/Providers/ScrapyardGTKServiceProvider.php` | `register()` and `boot()` are empty.[^provider] |

`GTKApplication::createWindow` builds a `GtkApplicationWindow`, sets title and default size. Default child is a vertical `GtkBox` (chrome) holding a `GtkFixed` (content). `$asGrid` still uses `GtkGrid` as the window child. Menu bar prepends onto chrome, not into the Fixed.

[^app]: GTKApplication
[^surface]: GTKWindowSurface
[^provider]: ScrapyardGTKServiceProvider
