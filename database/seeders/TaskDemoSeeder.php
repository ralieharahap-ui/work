<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskProject;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'pt-gep')->first();
        if (!$org || Task::where('organization_id', $org->id)->exists()) {
            return;
        }

        $admin = User::where('organization_id', $org->id)->where('email', 'admin@pt-gep.com')->first();
        if (!$admin) {
            return;
        }

        $project = TaskProject::create([
            'organization_id' => $org->id,
            'owner_id'        => $admin->id,
            'title'           => 'Ekspansi Sumber Cangkang Sawit Q3',
            'description'     => 'Menambah jaringan supplier cangkang sawit di wilayah Sumatera Selatan.',
            'status'          => 'Ongoing',
            'end_date'        => now()->addMonths(2),
        ]);

        $samples = [
            ['title' => 'Survei lokasi supplier baru', 'category' => 'Strategis', 'status' => 'In Progress', 'priority' => 'High', 'days' => 5],
            ['title' => 'Rekap surat jalan mingguan', 'category' => 'Operasional', 'status' => 'To Do', 'priority' => 'Medium', 'days' => 2],
            ['title' => 'Negosiasi harga kontrak supplier', 'category' => 'Strategis', 'status' => 'Review', 'priority' => 'Urgent', 'days' => 1],
            ['title' => 'Update data titik bongkar PLTU', 'category' => 'Operasional', 'status' => 'To Do', 'priority' => 'Low', 'days' => 10],
            ['title' => 'Audit stok gudang bulanan', 'category' => 'Operasional', 'status' => 'Blocked', 'priority' => 'High', 'days' => -2],
        ];

        foreach ($samples as $s) {
            Task::create([
                'organization_id' => $org->id,
                'project_id'      => $s['category'] === 'Strategis' ? $project->id : null,
                'pic_id'          => $admin->id,
                'created_by'      => $admin->id,
                'title'           => $s['title'],
                'category'        => $s['category'],
                'status'          => $s['status'],
                'priority'        => $s['priority'],
                'deadline'        => now()->addDays($s['days']),
            ]);
        }
    }
}
