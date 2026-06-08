<?php

use App\Models\RegistrarTld;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrar_tlds', function (Blueprint $table) {
            $table->string('whois_link_template', 512)->nullable()->after('tld');
        });

        $defaults = [
            'pt' => 'https://www.pt.pt/pt/ferramentas/whois/detalhes/?site={site}&tld={tld_dot}',
            'com' => 'https://rdap.registrar.cloudflare.com/{fqdn}/whois',
            'net' => 'https://rdap.registrar.cloudflare.com/{fqdn}/whois',
            'org' => 'https://rdap.registrar.cloudflare.com/{fqdn}/whois',
        ];

        foreach ($defaults as $tld => $template) {
            RegistrarTld::query()
                ->where('tld', $tld)
                ->whereNull('whois_link_template')
                ->update(['whois_link_template' => $template]);
        }
    }

    public function down(): void
    {
        Schema::table('registrar_tlds', function (Blueprint $table) {
            $table->dropColumn('whois_link_template');
        });
    }
};
