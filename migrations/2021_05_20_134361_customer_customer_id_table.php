<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerIdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'customer_io_customers',
            function (Blueprint $table) {

                $table->increments('id');

                $table->string('uuid', 64)->index();
                $table->string('email')->index();

                $table->string('workspace_name')->index();
                $table->string('workspace_id')->index();
                $table->string('site_id')->index();

                $table->timestamp('created_at')->index();
                $table->timestamp('updated_at')->index();
                $table->timestamp('deleted_at')->nullable()->index();

            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leadtracker_leads');
    }
}