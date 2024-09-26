<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequirementFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('requirement_files', function (Blueprint $table) {
            $table->id();
            $table->string('requirement_id');
            $table->string('filename');
            $table->string('path')->nullable();
            $table->string('size')->nullable();
            $table->string('folder_id')->nullable();
            $table->string('org_log_id');
            $table->string('role');
            $table->string('status')->default('A');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    
    public function down()
    {
        Schema::dropIfExists('requirement_files');
    }
}
