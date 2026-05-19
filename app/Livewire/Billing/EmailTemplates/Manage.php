<?php

namespace App\Livewire\Billing\EmailTemplates;

use App\Models\EmailTemplate;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Manage extends Component
{
    public string $placeholderSyntax = '{{variable}}';

    public ?EmailTemplate $template = null;

    public string $name = '';

    public string $slug = '';

    public string $subject = '';

    public string $body = '';

    public string $description = '';

    public string $variables_input = '';

    public bool $is_active = true;

    public function mount(?EmailTemplate $template = null): void
    {
        $this->template = $template;
        if ($template) {
            $this->name = $template->name;
            $this->slug = $template->slug;
            $this->subject = $template->subject;
            $this->body = $template->body;
            $this->description = (string) ($template->description ?? '');
            $this->variables_input = $template->variables !== null
                ? implode(', ', $template->variables)
                : '';
            $this->is_active = $template->is_active;
        }
    }

    public function updatedName(string $value): void
    {
        if ($this->template !== null || trim($this->slug) !== '') {
            return;
        }

        $this->slug = Str::slug($value, '_');
    }

    public function save(): void
    {
        $slugRule = ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_]*$/'];
        $slugRule[] = Rule::unique('email_templates', 'slug')->ignore($this->template?->id);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'variables_input' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $variables = collect(explode(',', $this->variables_input))
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->values()
            ->all();

        $payload = [
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject,
            'body' => $this->body,
            'description' => $this->description !== '' ? $this->description : null,
            'variables' => $variables !== [] ? $variables : null,
            'is_active' => $this->is_active,
        ];

        if ($this->template) {
            $this->template->update($payload);
            Flux::toast(variant: 'success', text: __('Email template updated.'));
            $this->redirect(route('billing.email-templates.edit', $this->template), navigate: true);

            return;
        }

        $template = EmailTemplate::query()->create($payload);
        Flux::toast(variant: 'success', text: __('Email template created.'));
        $this->redirect(route('billing.email-templates.edit', $template), navigate: true);
    }

    public function render()
    {
        $title = $this->template ? __('Edit email template') : __('New email template');

        return view('livewire.billing.email-templates.manage', compact('title'))->title($title);
    }
}
