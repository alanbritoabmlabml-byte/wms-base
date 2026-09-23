@props(['label', 'value', 'unit' => null, 'delta' => null, 'deltaKind' => '', 'spark' => null])
<div {{ $attributes->merge(['class' => 'card kpi']) }}>
  <span class="lbl">{{ $label }}</span>
  <span class="val">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</span>
  @if($delta)<span class="delta {{ $deltaKind }}">{!! $delta !!}</span>@endif
  @if($spark){!! \App\Support\Chart::sparkline($spark) !!}@endif
</div>
