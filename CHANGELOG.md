# Changelog

## 2.0.0 (2026-11-27)

The Poggit virion version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/libasynform/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) and **PHP 8.4+**.
- Distributed with Composer: the Poggit virion files (`.poggit.yml`, `virion.yml`) are removed and the misnamed
  `conposer.json` is now `composer.json`. [await-generator](https://github.com/SOF3/await-generator) is the Composer
  package `sof3/await-generator` (`^3.6.1`) instead of a bundled virion.
- `CustomForm::addSlider()` takes `?int $step = null, ?int $default = null` instead of the `-1` sentinels, so a
  negative step or default (for example on a `-5..5` slider) can be set. Pass `null` (or nothing) for "not set".
- `SimpleForm::addButton()` takes `?int $imageType = null` instead of `-1` for "no image", and throws
  `InvalidArgumentException` for anything other than `IMAGE_TYPE_PATH` / `IMAGE_TYPE_URL` (it used to treat every
  other value as a URL).
- `CustomForm::addContent()` throws `InvalidArgumentException` when the label is already used by another element
  (the later value used to silently overwrite the earlier one).

### 🐛 Fixes
- A `CustomForm` containing a label, header or divider could never be answered: the client reports a value for every
  element, but only the value elements were counted, so every response failed with "Expected an array response with
  the size N". Read-only elements are now accepted and left out of the processed response.
- A form sent to a player who could not receive it (disconnected) left the generator waiting forever and blocked
  later sends with "Player is already viewing a form". `send()` now rejects with `FormValidationException`.
- A failing `send()` (for example a JSON encoding error) left a stale pending entry behind. It is now cleaned up.
- Server-settings forms: when the JSON encoding failed the code rejected but kept going and passed `false` to
  `ServerSettingsResponsePacket::create()` (`TypeError`). The failure now stops the send, and the form id is only
  consumed once the form is encoded.
- Pending forms were keyed by `spl_object_id($player)`, which PHP reuses after a player object is freed, so a player
  who quit with a form open could poison the next player who got the same id. They are now kept in a `WeakMap` keyed
  by the player.
- `SimpleForm` without any button sent no `"buttons"` key, which the client rejects. It now sends an empty list.
- `CustomForm` sent `"default": null` for dropdowns and inputs without a default. The key is now omitted.

### 🧪 Tests
- PHPUnit tests (real `Player::sendForm()` / `Player::onFormSubmit()` with a recording network session, driven by
  `Await::f2c`) and GitHub Actions CI on PHP 8.4 / 8.5.
