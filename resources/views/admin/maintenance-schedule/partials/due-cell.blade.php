{{-- DataTables cell: next due text, flagged when the schedule is due. --}}
<p @class(['text-sm', 'font-medium text-destructive' => $due])>{{ $text }}</p>
@if ($due)
    <span class="badge mt-1" data-tone="danger">Due now</span>
@endif
