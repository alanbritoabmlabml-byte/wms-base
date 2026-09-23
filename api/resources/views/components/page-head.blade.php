@props(['title', 'sub' => null, 'crumbs' => null])
<div class="page-head">
  <div>
    @if($crumbs)<div class="crumbs">{!! $crumbs !!}</div>@endif
    <h1>{{ $title }}</h1>
    @if($sub)<div class="sub">{!! $sub !!}</div>@endif
  </div>
  @if(trim($slot))<div class="actions">{{ $slot }}</div>@endif
</div>
