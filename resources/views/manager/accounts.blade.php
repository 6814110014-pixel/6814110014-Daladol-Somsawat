@extends('layouts.app')
@section('title','บัญชีลูกค้าและพนักงานขาย')
@section('content')
<div class="page-heading"><div><span class="eyebrow">STORE ACCOUNT DIRECTORY</span><h1>บัญชีลูกค้าและพนักงานขาย</h1><p>ตรวจสอบชื่อ อีเมล ประเภทบัญชี และสถานะการใช้งานของคนในร้าน</p></div><a class="btn primary" href="{{ route('manager.accounts.create') }}">+ เพิ่มบัญชี</a></div>
<form class="filters" method="get" action="{{ route('manager.accounts') }}">
    <input name="q" value="{{ request('q') }}" class="search-input" placeholder="ค้นหาชื่อหรืออีเมล" aria-label="ค้นหาชื่อหรืออีเมล">
    <select name="type" aria-label="ประเภทบัญชี"><option value="">ทุกประเภท</option value="customer" @selected(request('type')==='customer')>ลูกค้า</option><option value="seller" @selected(request('type')==='seller')>พนักงานขาย</option></select>
    <button class="btn secondary">ค้นหา</button><a class="text-link" href="{{ route('manager.accounts') }}">ล้างตัวกรอง</a>
</form>
<section class="panel"><div class="panel-heading"><h2>พบ {{ number_format($accounts->total()) }} บัญชี</h2></div><div class="table-wrap"><table><thead><tr><th>ชื่อบัญชี</th><th>อีเมล</th><th>ประเภท</th><th>สถานะ</th><th>จัดการบัญชี</th></tr></thead><tbody>
@forelse($accounts as $account)
@php($enabled=$account->active && ($account->role!=='customer' || $account->member?->active))
<tr><td><strong>{{ $account->name }}</strong>@if($account->member)<small>{{ $account->member->player_code }}</small>@endif</td><td>{{ $account->email }}</td><td><span class="badge">{{ $account->role==='customer'?'ลูกค้า':'พนักงานขาย' }}</span></td><td><span class="badge {{ $enabled?'good':'' }}">{{ $enabled?'ใช้งาน':'ปิดใช้งาน' }}</span></td><td><a class="text-link" href="{{ route('manager.accounts.edit',$account) }}">แก้ไขบัญชี</a>@if($account->member)<br><a class="text-link" href="{{ route('members.show',$account->member) }}">เครดิต & โรงรถ</a>@endif<form method="post" action="{{ route('manager.accounts.destroy',$account) }}" data-confirm="ยืนยันลบบัญชี {{ $account->name }}? บัญชีจะเข้าสู่ระบบไม่ได้ แต่ประวัติธุรกรรมยังคงอยู่">@csrf @method('delete')<button class="btn secondary danger" type="submit">ลบบัญชี</button></form></td></tr>
@empty<tr><td colspan="5" class="empty">ไม่พบบัญชีตามเงื่อนไขที่เลือก</td></tr>@endforelse
</tbody></table></div>{{ $accounts->links('pagination') }}</section>
@endsection
