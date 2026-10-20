{{-- A homepage section heading: eyebrow, heading, highlight and, when $subtitle, a subtitle. --}}
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.field label="Eyebrow" :name="$section.'[eyebrow]'" hint="Small label above.">
        <x-ui.input :name="$section.'[eyebrow]'" :value="$landing[$section]['eyebrow']" maxlength="40" />
    </x-ui.field>
    <x-ui.field label="Heading" :name="$section.'[heading]'" required>
        <x-ui.input :name="$section.'[heading]'" :value="$landing[$section]['heading']" required maxlength="120" />
    </x-ui.field>
    <x-ui.field label="Highlight" :name="$section.'[highlight]'" hint="In colour after the heading.">
        <x-ui.input :name="$section.'[highlight]'" :value="$landing[$section]['highlight']" maxlength="80" />
    </x-ui.field>
    @if ($subtitle)
        <x-ui.field label="Subtitle" :name="$section.'[subtitle]'" class="sm:col-span-3">
            <x-ui.textarea :name="$section.'[subtitle]'" rows="2" maxlength="400" :value="$landing[$section]['subtitle']" />
        </x-ui.field>
    @endif
</div>
