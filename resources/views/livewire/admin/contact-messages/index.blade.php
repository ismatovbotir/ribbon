{{-- Buyer PII (phone numbers): Super Admin only, enforced by the component. Admin panel: straight, violet. --}}
<div>
    <x-page-header :title="__('admin.cms.contact.title')" :subtitle="__('admin.cms.contact.subtitle')" />

    <div class="mb-4">
        <label for="cm-status" class="mb-1 block text-xs font-medium text-text-secondary">{{ __('admin.cms.contact.filter_status') }}</label>
        <x-select id="cm-status" wire:model.live="status" class="max-w-xs">
            <option value="">{{ __('admin.cms.contact.filter_all') }}</option>
            <option value="new">{{ __('admin.cms.contact.status_new') }}</option>
            <option value="handled">{{ __('admin.cms.contact.status_handled') }}</option>
        </x-select>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse">
                <thead class="bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.cms.contact.col_phone') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.contact.col_message') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.contact.col_source') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.contact.col_attribution') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.contact.col_status') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.cms.contact.col_received') }}</th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.cms.contact.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="status, markHandled" class="divide-y divide-border">
                    @forelse ($messages as $message)
                        @php
                            $utmSource = $message->utm['utm_source'] ?? null;
                            $utmMedium = $message->utm['utm_medium'] ?? null;
                            $refHost = $message->referrer ? parse_url($message->referrer, PHP_URL_HOST) : null;
                            $attribution = collect([$utmSource, $utmMedium, $refHost])->filter()->implode(' / ');
                        @endphp
                        <tr wire:key="cm-{{ $message->id }}" class="align-top text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $message->phone) }}" class="font-mono text-accent-700 tabular-nums hover:underline">{{ $message->phone }}</a>
                                <p class="mt-0.5 font-mono text-xs text-text-muted uppercase">{{ $message->locale }}</p>
                            </td>
                            <td class="max-w-md px-4 py-3">
                                @if ($message->message)
                                    <p class="break-words whitespace-pre-line">{{ $message->message }}</p>
                                @else
                                    <p class="text-text-muted">{{ __('admin.cms.contact.no_message') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-text-secondary">{{ __('admin.cms.contact.source_'.$message->source) }}</td>
                            <td class="px-4 py-3 text-xs text-text-secondary">{{ $attribution !== '' ? $attribution : __('admin.cms.contact.direct') }}</td>
                            <td class="px-4 py-3">
                                @if ($message->status === 'handled')
                                    <x-badge variant="success" dot>{{ __('admin.cms.contact.status_handled') }}</x-badge>
                                    @if ($message->handledBy)
                                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.cms.contact.handled_by', ['name' => $message->handledBy->name]) }}</p>
                                    @endif
                                @else
                                    <x-badge variant="warning" dot>{{ __('admin.cms.contact.status_new') }}</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs whitespace-nowrap text-text-secondary tabular-nums">{{ $message->created_at->translatedFormat('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($message->status !== 'handled')
                                    <x-button variant="secondary" size="sm" wire:click="markHandled({{ $message->id }})" wire:loading.attr="disabled" wire:target="markHandled">{{ __('admin.cms.contact.mark_handled') }}</x-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-text-primary">{{ __('admin.cms.contact.empty_title') }}</p>
                                <p class="mt-1 text-sm text-text-secondary">{{ __('admin.cms.contact.empty_body') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($messages->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
</div>
