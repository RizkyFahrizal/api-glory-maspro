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
            $table->timestamp('last_assigned_at')->nullable()->after('queue_order');
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
            $table->dropColumn('last_assigned_at');
        });
    }
};
