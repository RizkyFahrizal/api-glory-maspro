<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wa_marketings', function (Blueprint $table) {
            $table->boolean('is_next_in_queue')->default(false)->after('last_assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wa_marketings', function (Blueprint $table) {
            $table->dropColumn('is_next_in_queue');
        });
    }
};
