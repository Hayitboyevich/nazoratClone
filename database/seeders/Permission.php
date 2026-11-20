<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class Permission extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['id' => 1, 'name' => 'update_response_category'],
            ['id' => 2, 'name' => 'send_response_inspector'],
            ['id' => 3, 'name' => 'reject_response_inspection'],
            ['id' => 4, 'name' => 'send_response_inspection'],
            ['id' => 5, 'name' => 'send_response_register'],
            ['id' => 6, 'name' => 'reject_response_register'],
            ['id' => 7, 'name' => 'send_response_court'],
            ['id' => 8, 'name' => 'send_response_fine'],
            ['id' => 9, 'name' => 'update_object_sphere'],
            ['id' => 10, 'name' => 'create_object_register'],
            ['id' => 11, 'name' => 'update_object_manual'],
            ['id' => 12, 'name' => 'create_object'],
            ['id' => 13, 'name' => 'check_object'],
            ['id' => 14, 'name' => 'change_object_status'],
            ['id' => 15, 'name' => 'change_object_location'],
            ['id' => 16, 'name' => 'delete_object_payment'],
            ['id' => 17, 'name' => 'change_object_deadline'],
            ['id' => 18, 'name' => 'create_payment'],
            ['id' => 19, 'name' => 'create_payment_laboratory'],
            ['id' => 20, 'name' => 'rotation_inspector'],
            ['id' => 21, 'name' => 'create_object_user'],
            ['id' => 22, 'name' => 'attach_object_inspector'],
            ['id' => 23, 'name' => 'change_object_inspector'],
            ['id' => 24, 'name' => 'change_object_price'],
            ['id' => 25, 'name' => 'create_object_laboratory_price'],
            ['id' => 26, 'name' => 'create_regulation'],
            ['id' => 27, 'name' => 'create_monitoring'],
            ['id' => 28, 'name' => 'accept_answer'],
            ['id' => 29, 'name' => 'accept_deed'],
            ['id' => 30, 'name' => 'accept_deed_cmr'],
            ['id' => 31, 'name' => 'accept_date'],
            ['id' => 32, 'name' => 'send_deed'],
            ['id' => 33, 'name' => 'ask_date'],
            ['id' => 34, 'name' => 'reject_date'],
            ['id' => 35, 'name' => 'reject_deed'],
            ['id' => 36, 'name' => 'reject_deed_cmr'],
            ['id' => 37, 'name' => 'reject_answer'],
            ['id' => 38, 'name' => 'create_fine'],
            ['id' => 39, 'name' => 'send_court'],
            ['id' => 40, 'name' => 'create_author_regulation'],
            ['id' => 41, 'name' => 'send_answer_author_regulation'],
            ['id' => 42, 'name' => 'regulation_change'],
            ['id' => 43, 'name' => 'send_check_list_file'],
            ['id' => 44, 'name' => 'accept_work_type'],
            ['id' => 45, 'name' => 'check_block'],
            ['id' => 46, 'name' => 'check_list_status_change'],
            ['id' => 47, 'name' => 'create_holiday'],
            ['id' => 48, 'name' => 'send_regulation_answer'],
        ];

        foreach ($permissions as $permission) {
            Permission::create(
                [
                    'id' => $permission['id'],
                    'name' => $permission['name'],
                ]
            );
        }
    }
}
