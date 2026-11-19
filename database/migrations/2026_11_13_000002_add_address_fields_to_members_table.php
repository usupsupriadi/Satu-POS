<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('province')->nullable()->after('member_region_id');
            $table->string('regency')->nullable()->after('province');
            $table->string('district')->nullable()->after('regency');
        });

        // Salin alamat lama dari wilayah sales (member_regions) agar data tidak hilang.
        DB::table('members')
            ->whereNotNull('member_region_id')
            ->orderBy('id')
            ->chunkById(200, function ($members): void {
                $regionIds = $members->pluck('member_region_id')->unique()->all();
                $regions = DB::table('member_regions')
                    ->whereIn('id', $regionIds)
                    ->get()
                    ->keyBy('id');

                foreach ($members as $member) {
                    $region = $regions->get($member->member_region_id);
                    if (! $region) {
                        continue;
                    }

                    DB::table('members')->where('id', $member->id)->update([
                        'province' => $region->province,
                        'regency' => $region->regency,
                        'district' => $region->district,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['province', 'regency', 'district']);
        });
    }
};
