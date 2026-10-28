<?php
namespace App\Services;
use App\Models\{Member,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerAccountService {
    public function create(array $data): User {
        return DB::transaction(function () use($data) {
            $member=Member::create(['player_code'=>'C-'.Str::ulid(),'name'=>$data['name'],
                'email'=>strtolower($data['email']),'phone'=>$data['phone']??null,'credits'=>0,'active'=>$data['active']??true]);
            return User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),
                'password'=>$data['password'],'role'=>'customer','active'=>$data['active']??true,'member_id'=>$member->id]);
        });
    }
    public function update(User $user,array $data): void {
        DB::transaction(function () use($user,$data) {
            $member=$user->member_id ? Member::whereKey($user->member_id)->lockForUpdate()->firstOrFail():null;
            $fields=['name'=>$data['name'],'email'=>strtolower($data['email'])];
            if(array_key_exists('active',$data))$fields['active']=$data['active'];
            if($member)$member->update([...$fields,'phone'=>$data['phone']??$member->phone]);
            if(!empty($data['password']))$fields['password']=$data['password'];
            $user->update($fields);
        });
    }
}
