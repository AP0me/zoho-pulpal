<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('shortened_urls', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->text('original'); // Column to store the original URL. Using text for potentially long URLs.
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('shortened_urls');
    }
};
