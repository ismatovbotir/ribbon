<?php

namespace App\Livewire\Admin\ContactMessages;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Buyer /contact messages — buyer PII, Super Admin only (enforced on every
 * request by bootAuthorizesAdmin and again in each action; Moderators never
 * reach this).
 */
class Index extends Component
{
    use AuthorizesAdmin;
    use WithPagination;

    protected function adminAccess(): ?string
    {
        return 'super-admin';
    }

    /** '' | new | handled */
    #[Url(history: true)]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->authorizeSuperAdmin();
        $this->resetPage();
    }

    public function markHandled(int $messageId): void
    {
        $this->authorizeSuperAdmin();

        ContactMessage::query()->findOrFail($messageId)->markHandled(Auth::user());
    }

    public function render()
    {
        $this->authorizeSuperAdmin();

        $messages = ContactMessage::query()
            ->with('handledBy:id,name')
            ->when(in_array($this->status, ContactMessage::STATUSES, true), fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.contact-messages.index', [
            'messages' => $messages,
        ])->layout('layouts.admin', [
            'title' => 'Contact messages',
            'breadcrumb' => [['label' => 'Contact messages']],
        ]);
    }
}
