<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('allocated_ram_gb')->nullable()->after('cancelled_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('default_allocated_ram_gb')->nullable()->after('description');
        });

        DB::table('services')->orderBy('id')->each(function (object $row) {
            if ($row->allocated_ram_mb === null) {
                return;
            }

            DB::table('services')->where('id', $row->id)->update([
                'allocated_ram_gb' => self::megabytesToGigabytes((int) $row->allocated_ram_mb),
            ]);
        });

        DB::table('products')->orderBy('id')->each(function (object $row) {
            if ($row->default_allocated_ram_mb === null) {
                return;
            }

            $mb = (int) $row->default_allocated_ram_mb;
            $gb = $mb <= 256 ? $mb : self::megabytesToGigabytes($mb);

            DB::table('products')->where('id', $row->id)->update([
                'default_allocated_ram_gb' => $gb,
            ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('allocated_ram_mb');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('default_allocated_ram_mb');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('allocated_ram_mb')->nullable()->after('cancelled_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('default_allocated_ram_mb')->nullable()->after('description');
        });

        DB::table('services')->orderBy('id')->each(function (object $row) {
            if ($row->allocated_ram_gb === null) {
                return;
            }

            DB::table('services')->where('id', $row->id)->update([
                'allocated_ram_mb' => (int) $row->allocated_ram_gb * 1024,
            ]);
        });

        DB::table('products')->orderBy('id')->each(function (object $row) {
            if ($row->default_allocated_ram_gb === null) {
                return;
            }

            DB::table('products')->where('id', $row->id)->update([
                'default_allocated_ram_mb' => (int) $row->default_allocated_ram_gb * 1024,
            ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('allocated_ram_gb');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('default_allocated_ram_gb');
        });
    }

    private static function megabytesToGigabytes(int $megabytes): int
    {
        if ($megabytes <= 0) {
            return 0;
        }

        return max(1, (int) round($megabytes / 1024));
    }
};
