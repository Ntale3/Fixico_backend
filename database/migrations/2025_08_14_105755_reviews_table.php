<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('destination_id')->constrained()->onDelete('cascade');
            $table->integer('rating');
            $table->text('comment');
            $table->timestamps();

            $table->unique(['user_id', 'destination_id']); // One review per user per destination
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
}
