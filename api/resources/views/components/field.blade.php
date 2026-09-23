@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'placeholder' => '', 'hint' => null, 'mono' => false])
<div class="field">
  <label for="f-{{ $name }}">{{ $label }}@if($required) <span style="color:var(--red)">*</span>@endif</label>
  @if($type === 'select')
    <select class="select" id="f-{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes }}>{{ $slot }}</select>
  @elseif($type === 'textarea')
    <textarea class="input" id="f-{{ $name }}" name="{{ $name }}" rows="2" placeholder="{{ $placeholder }}" {{ $attributes }}>{{ old($name, $value) }}</textarea>
  @else
    <input class="input {{ $mono ? 'mono' : '' }}" id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}" @required($required) {{ $attributes }}>
  @endif
  @if($hint)<span class="small muted">{{ $hint }}</span>@endif
  @error($name)<span class="form-error">{{ $message }}</span>@enderror
</div>
