<?php

use App\Models\Registrar;
use App\Models\RegistrarTld;

test('registrar tld builds pt whois url from template', function () {
    $tld = RegistrarTld::make([
        'tld' => 'pt',
        'whois_link_template' => 'https://www.pt.pt/pt/ferramentas/whois/detalhes/?site={site}&tld={tld_dot}',
    ]);

    expect($tld->whoisUrlForFqdn('admealhada.pt'))->toBe(
        'https://www.pt.pt/pt/ferramentas/whois/detalhes/?site=admealhada&tld=.pt',
    );
});

test('registrar tld builds cloudflare whois url from template', function () {
    $tld = RegistrarTld::make([
        'tld' => 'com',
        'whois_link_template' => 'https://rdap.registrar.cloudflare.com/{fqdn}/whois',
    ]);

    expect($tld->whoisUrlForFqdn('casadaavopeta.com'))->toBe(
        'https://rdap.registrar.cloudflare.com/casadaavopeta.com/whois',
    );
});

test('resolve whois url matches registrar tld from fqdn suffix', function () {
    $registrar = Registrar::query()->create([
        'name' => 'WHOIS Registrar',
        'code' => 'whois-'.uniqid(),
        'is_active' => true,
    ]);

    $ptTld = RegistrarTld::query()->create([
        'registrar_id' => $registrar->id,
        'tld' => 'pt',
        'whois_link_template' => 'https://www.pt.pt/pt/ferramentas/whois/detalhes/?site={site}&tld={tld_dot}',
    ]);

    expect(RegistrarTld::resolveWhoisUrl($registrar->id, null, 'example.pt'))->toBe(
        $ptTld->whoisUrlForFqdn('example.pt'),
    );
});
