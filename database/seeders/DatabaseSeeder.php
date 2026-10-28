<?php
namespace Database\Seeders;
use App\Models\{User,Member,Tank,CreditEntry,StockMovement};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        DB::transaction(function () {
            $admin=User::firstOrCreate(['email'=>'admin@tankdepot.test'],['name'=>'Depot Commander','password'=>'TankDepot!2026','role'=>'admin','active'=>true]);
            foreach ([['PLAYER-001','Iron Fox',50000],['PLAYER-002','Northern Star',35000],['PLAYER-003','Sakura Scout',25000]] as [$code,$name,$credits]) {
                $member=Member::firstOrCreate(['player_code'=>$code],['name'=>$name,'credits'=>0,'active'=>true]);
                if ($member->wasRecentlyCreated) {
                    $member->update(['credits'=>$credits]);
                    CreditEntry::create(['member_id'=>$member->id,'user_id'=>$admin->id,'amount'=>$credits,'balance_after'=>$credits,'note'=>'เครดิตเริ่มต้นสำหรับบัญชีสาธิต']);
                }
            }
            $prices=['tiger-i'=>15000,'panther'=>12000,'panzer-iv'=>8500,'m4-sherman'=>8000,'m3a1-stuart'=>4000,'m24-chaffee'=>6000,'t-34-76'=>7500,'t-34-85'=>9500,'is-2'=>14000,'type-95-ha-go'=>3000,'type-97-chi-ha'=>5500,'type-3-chi-nu'=>7000,'strv-m38'=>4500,'strv-m40l'=>5000,'strv-m42'=>8000];
            $stocks=[5,7,12,10,8,6,9,7,3,11,8,2,6,5,4];
            $records=json_decode(file_get_contents(database_path('data/tanks.json')),true,512,JSON_THROW_ON_ERROR);
            foreach ($records as $i=>$record) {
                $tank=Tank::firstOrCreate(['sku'=>$record['country_code'].'-'.strtoupper($record['slug'])],[
                    'name'=>$record['model'],'country'=>$record['country_code'],'category'=>$record['category'],'year'=>$record['year'],
                    'description'=>$record['description_th'],'history_note'=>$record['year_note'].($record['category_note']?' • '.$record['category_note']:''),
                    'source_title'=>$record['source_title'],'source_url'=>$record['source_url'],'image_file'=>$record['image_filename'],
                    'price'=>$prices[$record['slug']],'stock'=>$stocks[$i],'low_stock'=>3,'active'=>true,
                ]);
                if ($tank->wasRecentlyCreated) StockMovement::create(['tank_id'=>$tank->id,'user_id'=>$admin->id,'quantity'=>$tank->stock,'balance_after'=>$tank->stock,'note'=>'จำนวนเริ่มต้นในคลังจำลอง']);
            }
        });
    }
}
