<div>
    <x-page-header :title="__('sellers.employees.index.title')" :subtitle="__('sellers.employees.index.subtitle')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleAddForm">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                {{ __('sellers.employees.index.add_button') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Add Employee form — a simple inline card, matching the pattern used
         elsewhere for single-record create forms. --}}
    @if ($showAddForm)
        <div class="mb-6 rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.employees.add.title') }}</h2>
            </div>
            <form wire:submit="addEmployee" class="space-y-4 px-5 py-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.full_name_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input type="text" wire:model.blur="name" :error="$errors->has('name')" placeholder="{{ __('sellers.full_name_placeholder') }}" autofocus />
                    @error('name')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.email_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input type="email" wire:model.blur="email" :error="$errors->has('email')" placeholder="{{ __('sellers.email_placeholder') }}" />
                    @error('email')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.password_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input type="password" wire:model.blur="password" :error="$errors->has('password')" placeholder="{{ __('sellers.password_placeholder') }}" />
                    @error('password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.confirm_password_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input type="password" wire:model.blur="passwordConfirmation" :error="$errors->has('passwordConfirmation')" />
                    @error('passwordConfirmation')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.language_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-language-select wire:model="locale" :error="$errors->has('locale')" />
                    @error('locale')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <x-button type="button" variant="ghost" wire:click="toggleAddForm">{{ __('sellers.employees.add.cancel_button') }}</x-button>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addEmployee">
                        {{ __('sellers.employees.add.submit_button') }}
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('sellers.employees.index.table.name') }}</th>
                        <th class="px-4 py-2.5">{{ __('sellers.employees.index.table.email') }}</th>
                        <th class="px-4 py-2.5">{{ __('sellers.employees.index.table.role') }}</th>
                        <th class="px-4 py-2.5">{{ __('sellers.employees.index.table.language') }}</th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('sellers.employees.index.table.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($members as $member)
                        @php
                            $isOwnerRow = (int) $member->pivot->role_id === $ownerRoleId;
                        @endphp
                        <tr wire:key="member-{{ $member->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2 font-medium">{{ $member->name }}</td>
                            <td class="px-4 py-2 text-text-secondary">{{ $member->email }}</td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$isOwnerRow ? 'info' : 'muted'">
                                    {{ __('sellers.employees.role.'.($isOwnerRow ? 'owner' : 'employee')) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-2 text-text-secondary">{{ __('sellers.language_option.'.$member->locale) }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($isOwnerRow)
                                    <span class="text-xs text-text-muted" title="{{ __('sellers.employees.owner_note') }}">—</span>
                                @else
                                    <x-button variant="danger" size="sm" wire:click="confirmRemove({{ $member->id }})">
                                        {{ __('sellers.employees.remove_action') }}
                                    </x-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Remove confirmation modal --}}
    @if ($showRemoveConfirm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelRemove"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">
                    {{ __('sellers.employees.remove_confirm_title', ['name' => $this->removingUser?->name]) }}
                </h2>
                <p class="mt-2 text-sm text-text-secondary">{{ __('sellers.employees.remove_confirm_body') }}</p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="cancelRemove">{{ __('sellers.employees.remove_cancel') }}</x-button>
                    <x-button variant="danger-solid" wire:click="removeEmployee" wire:loading.attr="disabled" wire:target="removeEmployee">
                        {{ __('sellers.employees.remove_action') }}
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
