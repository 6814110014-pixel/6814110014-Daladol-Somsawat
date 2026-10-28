@props(['name'])
@php
$paths=[
 'dashboard'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 'pos'=>'M3 4h2l2 12h11l3-9H6 M9 20h.01 M17 20h.01',
 'tanks.index'=>'M3 10l9-6 9 6v10H3z M8 20v-8h8v8 M8 16h8',
 'members.index'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M17 4a4 4 0 0 1 0 7 M22 21v-2a4 4 0 0 0-3-4',
 'sales.index'=>'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6 M9 12h6',
 'credits'=>'M3 7h17l-4-4 M21 17H4l4 4',
 'reports'=>'M4 3v17h17 M8 16v-4 M13 16V8 M18 16V5',
 'guide'=>'M12 5v15 M12 5C9 3 5 3 2 4v15c3-1 7-1 10 1 3-2 7-2 10-1V4c-3-1-7-1-10 1',
 'profile'=>'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2',
 'garage'=>'M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10'
];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['profile'] }}"/></svg>
