@props(['id', 'title', 'width' => '880px'])
{{-- Modal controlado por Alpine: abrir con $dispatch('open-modal', '{{ $id }}') o x-on:click="$store.ui.open('{{ $id }}')" --}}
<template x-teleport="body">
  <div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $id }}') open = true" x-on:keydown.escape.window="open = false" x-cloak>
    <div class="scrim" :class="open && 'open'" x-on:click="open = false"></div>
    <div class="modal" :class="open && 'open'" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
      <div class="m-box" style="width:min({{ $width }},100%)">
        <div class="m-h"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="btn icon ghost" x-on:click="open = false" aria-label="Cerrar"><x-icon name="x" /></button></div>
        {{ $slot }}
      </div>
    </div>
  </div>
</template>
