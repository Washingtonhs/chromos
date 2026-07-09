<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aprovado_id')->constrained('aprovados')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->enum('nivel_confianca', ['exata', 'provavel', 'ambigua', 'sem_correspondencia']);
            $table->unsignedTinyInteger('pontuacao');
            $table->json('criterios');
            $table->json('candidatos_alternativos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondencias');
    }
};
