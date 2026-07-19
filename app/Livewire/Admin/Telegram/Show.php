<?php

namespace App\Livewire\Admin\Telegram;

use App\Models\Setting;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Services\TelegramBotService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Contact list + conversation thread + reply box for people who have
 * messaged the bot directly (see TelegramWebhookController — this is a
 * two-way inbox, distinct from the one-way NotifyTelegramOfNew* admin
 * notifications, which stay exactly as they were). Super Admin only.
 */
class Show extends Component
{
    #[Url(as: 'contact')]
    public ?int $selectedContactId = null;

    public string $replyText = '';

    #[Computed]
    public function contacts(): Collection
    {
        return TelegramContact::query()->orderByDesc('last_message_at')->get();
    }

    #[Computed]
    public function selectedContact(): ?TelegramContact
    {
        if ($this->selectedContactId) {
            $contact = $this->contacts->firstWhere('id', $this->selectedContactId);

            if ($contact) {
                return $contact;
            }
        }

        return $this->contacts->first();
    }

    // Named conversationMessages, not messages() — Livewire/Laravel's own
    // validation-error-bag internals reserve "messages" in ways that
    // collide badly with a same-named component computed property (hit a
    // real "array_merge(): Argument #1 must be of type array, Collection
    // given" crash from this exact collision while building this).
    #[Computed]
    public function conversationMessages(): Collection
    {
        return $this->selectedContact
            ? $this->selectedContact->messages()->orderBy('created_at')->get()
            : collect();
    }

    public function selectContact(int $contactId): void
    {
        $this->selectedContactId = $contactId;
        $this->replyText = '';
    }

    public function send(TelegramBotService $telegram): void
    {
        $this->validate([
            'replyText' => ['required', 'string', 'max:4096'],
        ]);

        $contact = $this->selectedContact;

        if (! $contact) {
            return;
        }

        $botToken = Setting::current()->effectiveTelegramBotToken();

        if (! $botToken) {
            $this->addError('replyText', 'No Telegram bot is connected — configure one in Settings first.');

            return;
        }

        $telegramMessageId = $telegram->sendMessage($botToken, $contact->chat_id, $this->replyText);

        if (! $telegramMessageId) {
            $this->addError('replyText', 'Telegram did not accept this message — it may not have been delivered.');

            return;
        }

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => 'out',
            'body' => $this->replyText,
            'telegram_message_id' => $telegramMessageId,
            'sent_by' => Auth::id(),
        ]);

        $this->replyText = '';
        unset($this->conversationMessages);
    }

    public function render()
    {
        return view('livewire.admin.telegram.show')->layout('layouts.admin', [
            'title' => 'Messages',
            'breadcrumb' => [
                ['label' => 'Messages'],
            ],
        ]);
    }
}
