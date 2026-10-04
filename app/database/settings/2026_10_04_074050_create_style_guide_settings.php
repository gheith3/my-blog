<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('style_guide.content', <<<'MARKDOWN'
# House style

## Language
- Posts are in Arabic (MSA narration, Omani dialect in dialogue).
- Keep dialect spellings in dialogue exactly as written, e.g. "انت", "عبد؟ نيتك تفضحينا؟".

## Proofreading scope
- Fix spelling, hamza, tanween, grammar and punctuation only.
- Never change word choice, imagery or sentence order without asking.
- Flag possible ambiguities as suggestions instead of fixing them.

## Spelling
- Indefinite accusative takes tanween on the alif: مباغتاً، أيضاً، طويلاً.
- No harakat, except where a word would be misread (e.g. كالذِّكر).
- Never vocalize deliberate double meanings (e.g. العرق: sweat / lineage).

## Punctuation
- Colon before quoted dialogue: قال: "...".
- Keep long comma-linked sentences when they hold one image or one breath.
- Use a full stop where the scene or the thought shifts.
- Straight double quotes "..." for dialogue.
MARKDOWN);
        $this->migrator->add('style_guide.version', 1);
        $this->migrator->add('style_guide.updated_at', now()->toIso8601String());
    }

    public function down(): void
    {
        $this->migrator->delete('style_guide.content');
        $this->migrator->delete('style_guide.version');
        $this->migrator->delete('style_guide.updated_at');
    }
};
