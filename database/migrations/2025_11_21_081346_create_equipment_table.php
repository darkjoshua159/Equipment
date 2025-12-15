<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();

            // Fields from $fillable in the Equipment Model
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('image')->nullable(); // Stores the path to the image

            // Price cast to decimal (float is generally avoided for currency)
            $table->decimal('price', 10, 2); 

            $table->string('status')->default('available'); // e.g., 'available', 'reserved', 'maintenance'
            
            // Foreign Key (Linking equipment to the user who created it)
            // This links to the 'id' column on the 'users' table
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};