<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body',
        'description',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Replace {{variable}} placeholders in subject and body.
     *
     * @param  array<string, string|int|float|null>  $data
     * @return array{subject: string, body: string}
     */
    public function render(array $data): array
    {
        $search = [];
        $replace = [];

        foreach ($data as $key => $value) {
            $search[] = '{{'.$key.'}}';
            $replace[] = (string) ($value ?? '');
        }

        return [
            'subject' => str_replace($search, $replace, $this->subject),
            'body' => str_replace($search, $replace, $this->body),
        ];
    }
}
