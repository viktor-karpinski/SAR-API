<?php

use App\Models\User;
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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->string('address')->nullable();
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
            $table->text('description')->nullable();
            $table->time('from');
            $table->time('till')->nullable();
            $table->string('status')->default('V Čakaní');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
