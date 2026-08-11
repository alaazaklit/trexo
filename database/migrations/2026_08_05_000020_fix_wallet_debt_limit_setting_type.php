<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

// Voyager's settings page only renders an editable input for a fixed set of
// types (text, text_area, rich_text_box, markdown_editor, code_editor,
// image/file, select_dropdown, radio_btn, checkbox) — 'number' isn't one of
// them, so the field seeded in 000019 would show a heading with no input.
return new class extends Migration
{
    public function up(): void
    {
        Setting::where('key', 'wallet.debt_limit')->update(['type' => 'text']);
    }

    public function down(): void
    {
        Setting::where('key', 'wallet.debt_limit')->update(['type' => 'number']);
    }
};
