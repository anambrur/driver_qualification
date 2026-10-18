{{-- Accidents or violations: a yes/no answer, then the rows when the answer is yes. --}}
@php($current = old($answer, $v[$answer]))
<x-ui.card :id="$id" :title="$title" class="scroll-mt-24">
    <div class="grid gap-4" x-data="{ answer: {{ \Illuminate\Support\Js::from($current) }} }">
        <x-ui.radio-group :name="$answer" :legend="$question" :value="$current" required x-on:change="answer = $event.target.value" />
        <div x-show="answer === 'yes'" @if ($current !== 'yes') x-cloak @endif>
            @include('admin.driver.form.repeater', ['rows' => $form->rows[$group], 'row' => 'admin.driver.form.'.\Illuminate\Support\Str::singular($group).'-row'])
        </div>
    </div>
</x-ui.card>
