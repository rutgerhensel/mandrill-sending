<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('esp', 64);
            $table->string('template_slug', 64);
            $table->string('subject', 256);
            $table->text('recipients_json');
            $table->mediumText('payload_json');
            $table->integer('attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('scheduled_emails');
    }
}