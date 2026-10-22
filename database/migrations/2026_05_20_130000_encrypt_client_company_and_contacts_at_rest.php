<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('company_name')->nullable()->change();
            $table->text('postal_code')->nullable()->change();
            $table->text('city')->nullable()->change();
            $table->text('country')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('vat_number')->nullable()->change();
        });

        foreach (DB::table('clients')->orderBy('id')->cursor() as $row) {
            DB::table('clients')->where('id', $row->id)->update([
                'company_name' => self::encryptPlaintext($row->company_name),
                'address_line' => self::encryptPlaintext($row->address_line),
                'postal_code' => self::encryptPlaintext($row->postal_code),
                'city' => self::encryptPlaintext($row->city),
                'country' => self::encryptPlaintext($row->country),
                'phone' => self::encryptPlaintext($row->phone),
                'vat_number' => self::encryptPlaintext($row->vat_number),
                'notes' => self::encryptPlaintext($row->notes),
            ]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'email']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('email')->change();
            $table->text('phone')->nullable()->change();
        });

        foreach (DB::table('contacts')->orderBy('id')->cursor() as $row) {
            DB::table('contacts')->where('id', $row->id)->update([
                'name' => self::encryptPlaintext($row->name),
                'email' => self::encryptPlaintext($row->email),
                'phone' => self::encryptPlaintext($row->phone),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('contacts')->orderBy('id')->cursor() as $row) {
            DB::table('contacts')->where('id', $row->id)->update([
                'name' => self::decryptIfEncrypted($row->name) ?? '',
                'email' => self::decryptIfEncrypted($row->email) ?? '',
                'phone' => self::decryptIfEncrypted($row->phone),
            ]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('phone', 40)->nullable()->change();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['client_id', 'email']);
        });

        foreach (DB::table('clients')->orderBy('id')->cursor() as $row) {
            DB::table('clients')->where('id', $row->id)->update([
                'company_name' => self::decryptIfEncrypted($row->company_name),
                'address_line' => self::decryptIfEncrypted($row->address_line),
                'postal_code' => self::decryptIfEncrypted($row->postal_code),
                'city' => self::decryptIfEncrypted($row->city),
                'country' => self::decryptIfEncrypted($row->country),
                'phone' => self::decryptIfEncrypted($row->phone),
                'vat_number' => self::decryptIfEncrypted($row->vat_number),
                'notes' => self::decryptIfEncrypted($row->notes),
            ]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('company_name')->nullable()->change();
            $table->string('postal_code', 32)->nullable()->change();
            $table->string('city', 120)->nullable()->change();
            $table->char('country', 2)->nullable()->change();
            $table->string('phone', 40)->nullable()->change();
            $table->string('vat_number', 32)->nullable()->change();
        });
    }

    private static function encryptPlaintext(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            Crypt::decrypt($value, false);

            return $value;
        } catch (Throwable) {
            return Crypt::encrypt($value, false);
        }
    }

    private static function decryptIfEncrypted(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decrypt($value, false);
        } catch (Throwable) {
            return $value;
        }
    }
};
