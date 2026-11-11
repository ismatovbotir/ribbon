<?php

namespace App\Services\Bot;

/**
 * One bot message for any channel. Buttons: list of
 * ['label' => string, 'action' => string, 'target' => string, 'url' => ?string].
 * A button with a url is a link; the rest are presses sent back to the engine.
 */
final class BotReply
{
    /**
     * @param  'answer'|'finder'|'fallback'|'form'  $kind
     * @param  list<array{label: string, action: string, target: string, url: ?string}>  $buttons
     * @param  'lead'|'handoff'|null  $form
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $text,
        public readonly array $buttons = [],
        public readonly ?int $answerId = null,
        public readonly ?string $matchedBy = null,
        public readonly ?string $form = null,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'text' => $this->text,
            'buttons' => $this->buttons,
            'answerId' => $this->answerId,
            'matchedBy' => $this->matchedBy,
            'form' => $this->form,
        ];
    }
}
