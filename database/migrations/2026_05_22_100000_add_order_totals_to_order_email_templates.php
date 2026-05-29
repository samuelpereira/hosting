<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['order_created', 'order_payment_reminder'] as $slug) {
            $template = EmailTemplate::query()->where('slug', $slug)->first();
            if ($template === null || str_contains($template->body, '{{order_totals}}')) {
                continue;
            }

            $body = str_replace(
                "{{order_lines}}\n\n<p>Dados",
                "{{order_lines}}\n\n{{order_totals}}\n\n<p>Dados",
                $template->body,
            );

            $variables = $template->variables ?? [];
            if (! in_array('order_totals', $variables, true)) {
                $variables[] = 'order_totals';
            }

            $template->update([
                'body' => $body,
                'variables' => $variables,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['order_created', 'order_payment_reminder'] as $slug) {
            $template = EmailTemplate::query()->where('slug', $slug)->first();
            if ($template === null || ! str_contains($template->body, '{{order_totals}}')) {
                continue;
            }

            $body = str_replace(
                "{{order_lines}}\n\n{{order_totals}}\n\n<p>Dados",
                "{{order_lines}}\n\n<p>Dados",
                $template->body,
            );

            $variables = array_values(array_filter(
                $template->variables ?? [],
                fn (string $var) => $var !== 'order_totals',
            ));

            $template->update([
                'body' => $body,
                'variables' => $variables,
            ]);
        }
    }
};
