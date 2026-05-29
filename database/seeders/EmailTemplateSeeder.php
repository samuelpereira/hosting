<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string,
     *     name: string,
     *     subject: string,
     *     body: string,
     *     description: string,
     *     variables: list<string>,
     * }>
     */
    private const TEMPLATES = [
        [
            'slug' => 'order_created',
            'name' => 'Encomenda criada',
            'subject' => 'Encomenda {{order_number}}',
            'description' => 'Enviado quando é criada uma nova encomenda para o cliente',
            'variables' => [
                'client_name',
                'client_email',
                'order_number',
                'order_total',
                'order_currency',
                'order_issued_date',
                'order_due_date',
                'order_lines',
                'order_totals',
                'company_name',
                'company_phone',
                'company_email',
                'company_bank_account',
            ],
            'body' => <<<'HTML'
<p>Estimado(a) {{client_name}},</p>

<p>Confirmamos a receção da sua encomenda <strong>{{order_number}}</strong>.</p>

<p><strong>Encomenda:</strong> {{order_number}}<br>
<strong>Data:</strong> {{order_issued_date}}<br>
<strong>Valor:</strong> {{order_total}} {{order_currency}}<br>
<strong>Data de vencimento:</strong> {{order_due_date}}</p>

{{order_lines}}

{{order_totals}}

<p>Dados para pagamento:</p>

<p><strong>{{company_bank_account}}</strong></p>

<p>Solicitamos que o pagamento seja efetuado até à respetiva data de vencimento. Caso tenha dúvidas, responda a este e-mail.</p>

<p>Agradecemos a sua preferência.</p>
<p>{{company_name}}<br>{{company_phone}}<br>{{company_email}}</p>
HTML,
        ],
        [
            'slug' => 'order_paid',
            'name' => 'Encomenda paga',
            'subject' => 'Pagamento recebido — encomenda {{order_number}}',
            'description' => 'Enviado quando a encomenda é marcada como paga',
            'variables' => [
                'client_name',
                'order_number',
                'order_total',
                'order_currency',
                'paid_at',
                'attachment_notice',
                'company_name',
                'company_phone',
                'company_email',
                'company_bank_account',
            ],
            'body' => <<<'HTML'
<p>Estimado(a) {{client_name}},</p>

<p>Confirmamos a receção do seu pagamento com sucesso.</p>

<p><strong>Encomenda:</strong> {{order_number}}<br>
<strong>Montante:</strong> {{order_total}} {{order_currency}}</p>

{{attachment_notice}}

<p>Agradecemos a sua preferência.</p>
<p>{{company_name}}<br>{{company_phone}}<br>{{company_email}}</p>
HTML,
        ],
        [
            'slug' => 'order_payment_reminder',
            'name' => 'Relembrar pagamento',
            'subject' => 'Lembrete: a encomenda {{order_number}} ainda se encontra por pagar',
            'description' => 'Enviado para relembrar que a encomenda continua por pagar',
            'variables' => [
                'client_name',
                'order_number',
                'order_total',
                'order_currency',
                'order_issued_date',
                'order_due_date',
                'order_lines',
                'order_totals',
                'company_name',
                'company_phone',
                'company_email',
                'company_bank_account',
            ],
            'body' => <<<'HTML'
<p>Estimado(a) {{client_name}},</p>

<p>A encomenda <strong>{{order_number}}</strong> ainda se encontra por pagar.</p>

<p><strong>Encomenda:</strong> {{order_number}}<br>
<strong>Data:</strong> {{order_issued_date}}<br>
<strong>Valor:</strong> {{order_total}} {{order_currency}}<br>
<strong>Data de vencimento:</strong> {{order_due_date}}</p>

{{order_lines}}

{{order_totals}}

<p>Dados para pagamento:</p>

<p><strong>{{company_bank_account}}</strong></p>

<p>Solicitamos que regularize o pagamento com a maior brevidade possível, de forma a evitar a interrupção dos serviços.</p>

<p>Caso já tenha efetuado o pagamento, por favor ignore esta mensagem.</p>

<p>Agradecemos a sua preferência.</p>
<p>{{company_name}}<br>{{company_phone}}<br>{{company_email}}</p>
HTML,
        ],
        [
            'slug' => 'service_welcome',
            'name' => 'Serviço Ativo',
            'subject' => 'O seu serviço {{product_name}} já se encontra ativo',
            'description' => 'Enviado quando o serviço é ativado',
            'variables' => [
                'client_name',
                'product_name',
                'domain',
                'service_id',
                'username',
                'password',
                'panel_url',
                'company_name',
                'company_phone',
                'company_email',
                'company_bank_account',
            ],
            'body' => <<<'HTML'
<p>Estimado(a) {{client_name}},</p>

<p>O seu serviço <strong>{{product_name}}</strong> já se encontra ativo.</p>

<p><strong>Domínio:</strong> {{domain}}</p>

<p><strong>Painel de controlo</strong><br>
<a href="{{panel_url}}">{{panel_url}}</a></p>

<p><strong>Dados de acesso</strong></p>
<ul>
    <li><strong>Nome de utilizador:</strong> {{username}}</li>
    <li><strong>Palavra-passe:</strong> {{password}}</li>
</ul>

<p>Caso tenha alguma dúvida ou necessite de apoio técnico, responda a este e-mail.</p>

<p>Agradecemos a sua preferência.</p>
<p>{{company_name}}<br>{{company_phone}}<br>{{company_email}}</p>
HTML,
        ],
        [
            'slug' => 'domain_expiry_reminder',
            'name' => 'Aviso de expiração de domínio',
            'subject' => 'O domínio {{domain}} expira a {{expires_at}}',
            'description' => 'Enviado para avisar que o domínio está prestes a expirar',
            'variables' => [
                'client_name',
                'domain',
                'expires_at',
                'renewal_amount',
                'currency',
                'company_name',
            ],
            'body' => <<<'HTML'
<p>Estimado(a) {{client_name}},</p>

<p>O seu domínio <strong>{{domain}}</strong> expira a <strong>{{expires_at}}</strong>.</p>

<p><strong>Preço de renovação:</strong> {{renewal_amount}} {{currency}}</p>

<p>A renovação deverá ser efetuada antes da data de expiração, de forma a garantir a continuidade do domínio.</p>

<p>Caso pretenda, poderemos tratar da renovação por si. Para isso, basta responder a este e-mail.</p>

<p>Agradecemos a sua preferência.</p>
<p>{{company_name}}<br>{{company_phone}}<br>{{company_email}}</p>
HTML,
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $row) {
            EmailTemplate::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'subject' => $row['subject'],
                    'body' => trim($row['body']),
                    'description' => $row['description'],
                    'variables' => $row['variables'],
                    'is_active' => true,
                ],
            );
        }
    }
}
