<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDestinationsTable extends Migration
{
    public function up()
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->enum('category', ['Tropical paradise', 'Adventure', 'Cultural'])->default('Tropical paradise');
            $table->string('location');
            $table->string('country');
            $table->json('packages')->nullable();
            $table->decimal('price_per_person', 10, 2);
            $table->integer('max_capacity');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days');
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('destinations');
    }
}

