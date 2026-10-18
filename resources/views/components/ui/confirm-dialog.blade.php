{{--
    One shared confirm step for plain forms, rendered once by the layout:
        <form data-confirm="Delete this plan?" data-confirm-text="..." data-confirm-button="Delete">
    resources/js/app.js opens it on submit and submits the form only on confirm. resources/js/dialogs.js
    also drives it for window.showAppAlert / showAppConfirm, swapping in one of the icons below.
--}}
<x-ui.dialog id="confirm-dialog" size="sm" icon="triangle-alert" title="Please confirm" aria-describedby="confirm-dialog-text">
    <p id="confirm-dialog-text" class="text-sm whitespace-pre-line text-muted-foreground"></p>
    @foreach (['triangle-alert', 'circle-help', 'circle-x', 'circle-check', 'info'] as $icon)
        <template data-dialog-icon="{{ $icon }}">{{ svg('lucide-'.$icon, 'size-5', ['aria-hidden' => 'true']) }}</template>
    @endforeach
    <x-slot:footer>
        <form method="dialog" class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button type="submit" variant="outline" value="cancel" data-confirm-cancel>Cancel</x-ui.button>
            <x-ui.button type="submit" variant="destructive" value="ok" data-confirm-ok>Confirm</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.dialog>
