{{--
    Two-pane inbox: contact list (left) + conversation thread and reply
    box (right) — see Livewire\Admin\Telegram\Show's docblock for what a
    "contact" here actually is (someone who messaged the bot directly, not
    an offer-request submitter).
--}}
<div>
    <x-page-header title="Messages" subtitle="Conversations with people who've messaged your Telegram bot directly." />

    @if ($this->contacts->isEmpty())
        <div class="rounded-md border border-border-strong bg-surface-raised px-6 py-12 text-center">
            <p class="text-sm font-medium text-text-primary">No conversations yet</p>
            <p class="mt-1 text-sm text-text-secondary">Contacts appear here once someone messages your bot on Telegram.</p>
            <a href="{{ route('admin.settings.show') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-accent-700 hover:underline">Set up the bot in Settings →</a>
        </div>
    @else
        <div class="flex h-[calc(100vh-14rem)] min-h-[28rem] overflow-hidden rounded-md border border-border-strong bg-surface-raised">
            {{-- Contact list --}}
            <div class="w-72 shrink-0 overflow-y-auto border-r border-border">
                @foreach ($this->contacts as $contact)
                    <button
                        type="button"
                        wire:click="selectContact({{ $contact->id }})"
                        wire:key="contact-{{ $contact->id }}"
                        class="block w-full border-b border-border px-4 py-3 text-left transition-colors {{ $this->selectedContact?->id === $contact->id ? 'bg-accent-50' : 'hover:bg-surface-hover' }}"
                    >
                        <p class="truncate text-sm font-medium text-text-primary">{{ $contact->displayName() }}</p>
                        <p class="mt-0.5 text-xs text-text-muted">{{ \App\Support\LocalizedDate::short($contact->last_message_at) }}</p>
                    </button>
                @endforeach
            </div>

            {{-- Thread --}}
            <div class="flex min-w-0 flex-1 flex-col">
                @if ($this->selectedContact)
                    <div class="border-b border-border bg-surface-subtle px-5 py-3">
                        <p class="text-sm font-semibold text-text-primary">{{ $this->selectedContact->displayName() }}</p>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4" x-data x-init="$el.scrollTop = $el.scrollHeight" wire:key="thread-{{ $this->selectedContact->id }}">
                        @forelse ($this->conversationMessages as $message)
                            <div class="flex {{ $message->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-md rounded-md px-3 py-2 text-sm {{ $message->direction === 'out' ? 'bg-accent-600 text-white' : 'bg-surface-subtle text-text-primary' }}">
                                    <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                                    <p class="mt-1 text-xs {{ $message->direction === 'out' ? 'text-accent-100' : 'text-text-muted' }}">{{ $message->created_at->format('M j, H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-text-muted">No messages yet.</p>
                        @endforelse
                    </div>

                    <form wire:submit="send" class="flex items-end gap-2 border-t border-border px-4 py-3">
                        <textarea
                            wire:model="replyText"
                            rows="2"
                            placeholder="Type a reply…"
                            class="block w-full resize-none rounded-sm border bg-surface px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('replyText') ? 'border-danger-600' : 'border-border' }}"
                        ></textarea>
                        <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">Send</x-button>
                    </form>
                    @error('replyText')
                        <p class="px-4 pb-3 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        </div>
    @endif
</div>
