<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ConsolidateDuplicateGiftInventoryRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Consolidate duplicate gift inventory records
        // Keep one record per user_id + gift_id combination, summing quantities
        
        DB::statement("
            CREATE TEMPORARY TABLE consolidated_gifts AS
            SELECT 
                user_id,
                gift_id,
                SUM(quantity) as total_quantity,
                MIN(received_at) as first_received_at,
                MAX(received_at) as last_received_at,
                MIN(received_from_user_id) as first_sender_id,
                is_converted,
                converted_at,
                MIN(id) as keep_id,
                GROUP_CONCAT(id) as all_ids
            FROM user_gift_inventory 
            GROUP BY user_id, gift_id, is_converted, converted_at
            HAVING COUNT(*) >= 1
        ");

        // Update the record we want to keep with consolidated data
        DB::statement("
            UPDATE user_gift_inventory ugi
            INNER JOIN consolidated_gifts cg ON ugi.id = cg.keep_id
            SET 
                ugi.quantity = cg.total_quantity,
                ugi.received_at = cg.last_received_at,
                ugi.received_from_user_id = cg.first_sender_id
        ");

        // Delete duplicate records (keep only the one with MIN(id))
        DB::statement("
            DELETE ugi FROM user_gift_inventory ugi
            INNER JOIN consolidated_gifts cg ON FIND_IN_SET(ugi.id, cg.all_ids) > 0
            WHERE ugi.id != cg.keep_id
        ");

        // Drop temporary table
        DB::statement("DROP TEMPORARY TABLE consolidated_gifts");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration cannot be easily reversed as we lose the original duplicate data
        // Consider creating a backup before running this migration if reversal is needed
    }
}
