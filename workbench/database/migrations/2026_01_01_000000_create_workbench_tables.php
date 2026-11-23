<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->timestamps();
        });

        Schema::create('actividad', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->timestamps();
        });

        Schema::create('actividad_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id');
            $table->unsignedBigInteger('estudiante_id');
            $table->decimal('nota')->nullable();
            $table->string('estado', 1)->default('N');
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
};
