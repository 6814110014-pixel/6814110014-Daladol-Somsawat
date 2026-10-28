@props(['tank','large'=>false])
<div {{ $attributes->class(['tank-visual','large'=>$large]) }}>
@if($tank->image_url)<img src="{{ $tank->image_url }}" alt="{{ $tank->name }}" loading="lazy">@else
<div class="visual-grid"></div><span class="image-slot">{{ $tank->sku }}</span>
<svg class="tank-outline" viewBox="0 0 260 120" aria-hidden="true"><path d="M35 80h166l19 13-10 16H36L22 95zM54 76l11-26h100l21 26M89 49l10-26h49l13 26M149 34h91v8h-85"/><path d="M40 88h162l7 7-9 7H39l-7-7z"/><circle cx="53" cy="95" r="7"/><circle cx="80" cy="95" r="7"/><circle cx="107" cy="95" r="7"/><circle cx="134" cy="95" r="7"/><circle cx="161" cy="95" r="7"/><circle cx="188" cy="95" r="7"/></svg>
<span class="image-caption">พื้นที่ภาพสินค้า · {{ $tank->image_file }}</span>@endif
</div>
