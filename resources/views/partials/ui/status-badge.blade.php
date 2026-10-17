{{-- Plain partial (not a component) so DataTables can render it per row cheaply. --}}
<span class="badge" data-tone="{{ \App\Support\Ui\Status::tone($status) }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ \App\Support\Ui\Status::label($status) }}</span>
