<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Contactos públicos ficam na tabela settings via Admin → Configurações.
        // Esta migration existia para um valor pessoal; não volta a gravar PII em instalações novas.
    }

    public function down(): void
    {
        //
    }
};
