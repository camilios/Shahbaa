<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use Illuminate\Database\Seeder;

class CheckpointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Checkpoint::create([
            'governorate'=>'الشام',
            'id'=> 1,
            'location'=>'شهبا',
            'name' => 'المحوري'
        ]);


        Checkpoint::create([
            'governorate'=>'الشام',
            'id'=> 2,
            'location'=>'لاهثة',
            'name' => 'الملعب'
        ]);

          Checkpoint::create([
            'governorate'=>'الشام',
            'id'=> 3,
            'location'=>'الصورى الكبيرة',
            'name' => 'عتيل'
        ]);


        Checkpoint::create([
            'governorate'=>'الشام',
            'id'=> 4,
            'location'=>'المسمية',
            'name' => 'شهبا'
        ]);

          Checkpoint::create([
            'governorate'=>'الشام',
            'id'=> 5,
            'location'=>'جرمانا',
            'name' => ''
        ]);



    }
}
