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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('listing_id', 50)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->bigInteger('price');
            $table->text('description');
            $table->string('bedrooms', 20);
            $table->string('bathrooms', 20);
            $table->integer('land_area');
            $table->integer('building_area');
            $table->string('property_type', 50)->default('Rumah');
            $table->text('address');
            $table->string('location', 100);
            $table->string('electricity', 50)->nullable();
            $table->string('certificate', 50)->nullable();
            $table->string('facing', 50)->nullable();
            $table->string('furnish', 50)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['available', 'sold'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
