{{-- Plain (non-DataTables) table. Below md, rows become cards when td carry data-label. --}}
@props(['stack' => true])
<div class="table-container">
    <table {{ $attributes->class(['table', 'table-stack' => $stack]) }}>
        {{ $slot }}
    </table>
</div>
