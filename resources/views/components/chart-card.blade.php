@props(['id', 'title', 'icon' => 'fa-line-chart', 'height' => 280, 'summary' => ''])
<div {{ $attributes->class(['box']) }}>
    <div class="box-header with-border">
        <h2 class="box-title"><i class="fa {{ $icon }}" aria-hidden="true"></i> <span id="{{ $id }}-title">{{ $title }}</span></h2>
        @isset($actions)<div class="box-tools pull-right">{{ $actions }}</div>@endisset
    </div>
    <div class="box-body">
        <figure class="ow-figure">
            <div class="ow-chart-container" style="height:{{ $height }}px"><canvas id="{{ $id }}"></canvas></div>
            <figcaption class="ow-caption" id="{{ $id }}-caption">{{ $summary }}</figcaption>
        </figure>
        @isset($table)
        <details class="ow-datatable">
            <summary>View as a data table</summary>
            {{ $table }}
        </details>
        @endisset
    </div>
</div>
