<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMailerRejectListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mailer_rejects', function (Blueprint $table)
        {
            $table->increments('id');
            
            $table->string('email')->unique();
            $table->string('reason');
            $table->string('detail');
            $table->boolean('expired')->default(0);
            
            $table->timestamp('added_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('expires_at')->nullable();
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
        Schema::drop('mailer_rejects');
    }
}
