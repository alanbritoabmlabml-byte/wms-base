@props(['title' => null, 'aside' => null, 'flush' => false])
<div {{ $attributes->merge(['class' => 'card']) }}>
  @if($title || $aside)
  <div class="card-h">
    <h3>{!! $title !!}</h3>
    @if($aside)<div>{!! $aside !!}</div>@endif
  </div>
  @endif
  @if($flush){{ $slot }}@else<div class="card-b">{{ $slot }}</div>@endif
</div>
