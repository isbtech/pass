<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccessCodesTable extends Migration
{
    public function up()
    {
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('audio_file_id')->constrained()->onDelete('cascade');
            $table->enum('validity_type', ['hours', 'days', 'playtime']);
            $table->integer('validity_value')->comment('Hours, days or seconds of playtime');
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_plays')->nullable()->comment('Max number of times file can be played');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('access_codes');
    }
}