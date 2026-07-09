<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprovados', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cpf_mascarado')->nullable();
            $table->string('instituicao');
            $table->string('curso');
            $table->string('modalidade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprovados');
    }
};
