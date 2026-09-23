@if($paginator->hasPages())
<div class="pager">
  @if($paginator->onFirstPage())<span class="btn" aria-disabled="true">‹</span>@else<a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
  @foreach($elements as $element)
    @if(is_string($element))<span class="btn" aria-disabled="true">{{ $element }}</span>@endif
    @if(is_array($element))
      @foreach($element as $page => $url)
        @if($page == $paginator->currentPage())<span class="btn primary">{{ $page }}</span>@else<a class="btn" href="{{ $url }}">{{ $page }}</a>@endif
      @endforeach
    @endif
  @endforeach
  @if($paginator->hasMorePages())<a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="btn" aria-disabled="true">›</span>@endif
</div>
@endif
