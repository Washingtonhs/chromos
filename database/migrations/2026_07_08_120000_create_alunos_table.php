<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_aluno')->unique();
            $table->string('nome_completo');
            $table->string('nome_normalizado');
            $table->string('sobrenome_normalizado')->index();
            $table->char('cpf', 11)->unique();
            $table->char('cpf_meio', 6)->index();
            $table->string('unidade');
            $table->string('turma');
            $table->unsignedSmallInteger('ano_matricula');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
