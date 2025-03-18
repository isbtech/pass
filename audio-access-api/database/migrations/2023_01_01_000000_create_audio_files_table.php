<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAudioFilesTable extends Migration
{
    public function up()
    {
        Schema::create('audio_files', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('filename', 255);
            $table->string('file_path', 255);
            $table->integer('file_size');
            $table->integer('duration')->comment('Duration in seconds');
            $table->timestamp('upload_date')->useCurrent();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audio_files');
    }
}