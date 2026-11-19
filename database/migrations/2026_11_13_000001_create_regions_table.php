<?php

use App\Services\Regions\RegionImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 10);
            $table->string('province_name');
            $table->string('regency_code', 10);
            $table->string('regency_name');
            $table->string('district_code', 15)->unique();
            $table->string('district_name');
            $table->timestamps();

            $table->index(['province_name', 'regency_name']);
            $table->index('province_name');
        });

        (new RegionImporter)->import();
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
